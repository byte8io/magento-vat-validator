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
        $endpoint = rtrim($this->config->getHmrcEndpoint($storeId), '/') . '/' . rawurlencode($number);

        $requester = $this->config->getRequesterVatNumber($storeId);
        if ($requester !== null) {
            $endpoint .= '/' . rawurlencode($requester);
        }

        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($this->config->getTimeout($storeId));
            $curl->addHeader('Accept', self::HMRC_HEADER_ACCEPT);
            $curl->get($endpoint);

            $status = $curl->getStatus();
            $body = (string) $curl->getBody();

            if ($status === 404) {
                return $this->buildResult($country, $number, ValidationResultInterface::STATUS_INVALID, 'HMRC: VAT number not found');
            }

            if ($status < 200 || $status >= 300) {
                $this->logger->warning(sprintf('HMRC returned HTTP %d for GB%s', $status, $number));

                return $this->buildResult($country, $number, ValidationResultInterface::STATUS_UNAVAILABLE, 'HTTP ' . $status);
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
