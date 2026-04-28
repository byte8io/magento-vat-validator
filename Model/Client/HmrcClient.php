<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model\Client;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Byte8\VatValidator\Model\Config;
use Byte8\VatValidator\Model\ValidationResult;
use Byte8\VatValidator\Model\ValidationResultFactory;
use Magento\Framework\HTTP\Client\CurlFactory;
use Psr\Log\LoggerInterface;

class HmrcClient
{
    private const HMRC_HEADER_ACCEPT = 'application/vnd.hmrc.2.0+json';

    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly ValidationResultFactory $resultFactory,
        private readonly HmrcOAuthTokenProvider $tokenProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    public function supports(string $countryCode): bool
    {
        return strtoupper($countryCode) === 'GB';
    }

    public function validate(string $countryCode, string $vatNumber, ?int $storeId = null): ValidationResult
    {
        $country = strtoupper($countryCode);
        $number = $vatNumber;

        // Format pre-flight: HMRC requires a 9- or 12-digit VRN. A 13-digit
        // input is a typo (e.g. duplicated digit), not a transient outage —
        // return STATUS_INVALID so the result persists to the audit log and
        // a re-attempt during checkout hits the cache and surfaces a notice.
        $digitsOnly = preg_replace('/[^0-9]/', '', $number) ?? '';
        $digitLength = strlen($digitsOnly);
        if ($digitLength !== 9 && $digitLength !== 12) {
            return $this->buildResult(
                $country,
                $number,
                ValidationResultInterface::STATUS_INVALID,
                sprintf('UK VAT must be 9 or 12 digits (got %d).', $digitLength)
            );
        }

        $endpoint = rtrim($this->config->getHmrcEndpoint($storeId), '/') . '/' . rawurlencode($number);

        $requesterDigits = $this->normaliseRequesterVrn($this->config->getRequesterVatNumber($storeId));
        if ($requesterDigits !== null) {
            $endpoint .= '/' . rawurlencode($requesterDigits);
        } elseif ($this->config->getRequesterVatNumber($storeId) !== null) {
            $this->logger->warning('HMRC requester VAT is configured but not in 9- or 12-digit format — omitting from lookup. Re-enter the digits-only VRN under General → Requester VAT Number.');
        }

        $accessToken = $this->tokenProvider->getAccessToken($storeId);
        if ($accessToken === null) {
            $this->logger->warning(sprintf(
                'HMRC validation skipped for GB%s — no OAuth access token (configure HMRC client_id / client_secret in admin).',
                $number
            ));

            return $this->buildResult($country, $number, ValidationResultInterface::STATUS_UNAVAILABLE, 'HMRC OAuth credentials not configured or token endpoint unreachable');
        }

        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($this->config->getTimeout($storeId));
            $curl->setOption(CURLOPT_CONNECTTIMEOUT, $this->config->getConnectTimeout($storeId));
            $curl->addHeader('Accept', self::HMRC_HEADER_ACCEPT);
            $curl->addHeader('Authorization', 'Bearer ' . $accessToken);
            $curl->get($endpoint);

            $status = $curl->getStatus();
            $body = (string) $curl->getBody();

            if ($status === 404) {
                return $this->buildResult($country, $number, ValidationResultInterface::STATUS_INVALID, 'HMRC: VAT number not found');
            }

            if ($status === 401 || $status === 403) {
                $this->logger->warning(sprintf(
                    'HMRC returned HTTP %d for GB%s — bearer token rejected. Verify the application is subscribed to "Check a UK VAT number" v2.0 and the client_id/client_secret are correct.',
                    $status,
                    $number
                ));

                return $this->buildResult($country, $number, ValidationResultInterface::STATUS_UNAVAILABLE, 'HMRC ' . $status . ': bearer token rejected');
            }

            if ($status < 200 || $status >= 300) {
                $this->logger->warning(sprintf(
                    'HMRC returned HTTP %d for GB%s — body: %s',
                    $status,
                    $number,
                    $this->summariseBody($body)
                ));

                return $this->buildResult(
                    $country,
                    $number,
                    ValidationResultInterface::STATUS_UNAVAILABLE,
                    sprintf('HTTP %d: %s', $status, $this->summariseBody($body))
                );
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                return $this->buildResult($country, $number, ValidationResultInterface::STATUS_UNAVAILABLE, 'Malformed HMRC response');
            }

            $target = $data['target'] ?? [];
            $name = $target['name'] ?? null;
            $address = isset($target['address']) && is_array($target['address'])
                ? $this->flattenAddress($target['address'])
                : null;

            return $this->resultFactory->create(['data' => [
                'country_code' => $country,
                'vat_number' => $number,
                'status' => ValidationResultInterface::STATUS_VALID,
                'source' => ValidationResultInterface::SOURCE_HMRC,
                'name' => $name,
                'address' => $address,
                'request_identifier' => $data['consultationNumber'] ?? null,
                'request_payload' => $endpoint,
                'response_payload' => $body,
            ]]);
        } catch (\Throwable $e) {
            $this->logger->error('HMRC validate error: ' . $e->getMessage());

            return $this->buildResult($country, $number, ValidationResultInterface::STATUS_UNAVAILABLE, $e->getMessage());
        }
    }

    private function normaliseRequesterVrn(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
        $length = strlen($digits);

        return ($length === 9 || $length === 12) ? $digits : null;
    }

    private function summariseBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '(empty body)';
        }

        $data = json_decode($trimmed, true);
        if (is_array($data)) {
            $code = (string) ($data['code'] ?? '');
            $message = (string) ($data['message'] ?? '');
            if ($code !== '' || $message !== '') {
                return trim($code . ': ' . $message, ': ');
            }
        }

        return strlen($trimmed) > 200 ? substr($trimmed, 0, 200) . '…' : $trimmed;
    }

    private function flattenAddress(array $address): string
    {
        $parts = [];
        foreach (['line1', 'line2', 'line3', 'line4', 'postcode', 'countryCode'] as $field) {
            if (!empty($address[$field])) {
                $parts[] = (string) $address[$field];
            }
        }

        return implode(', ', $parts);
    }

    private function buildResult(string $country, string $number, string $status, ?string $message): ValidationResult
    {
        return $this->resultFactory->create(['data' => [
            'country_code' => $country,
            'vat_number' => $number,
            'status' => $status,
            'source' => ValidationResultInterface::SOURCE_HMRC,
            'message' => $message,
        ]]);
    }
}
