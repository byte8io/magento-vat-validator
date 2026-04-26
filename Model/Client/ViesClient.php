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

class ViesClient
{
    public const SUPPORTED_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES',
        'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly ValidationResultFactory $resultFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function supports(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::SUPPORTED_COUNTRIES, true);
    }

    public function validate(string $countryCode, string $vatNumber, ?int $storeId = null): ValidationResult
    {
        $country = strtoupper($countryCode);
        $number = $vatNumber;

        $payload = [
            'countryCode' => $country,
            'vatNumber' => $number,
        ];

        $requesterCountry = $this->config->getRequesterCountry($storeId);
        $requesterVat = $this->config->getRequesterVatNumber($storeId);
        if ($requesterCountry !== null && $requesterVat !== null) {
            $payload['requesterMemberStateCode'] = $requesterCountry;
            $payload['requesterNumber'] = $requesterVat;
        }

        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($this->config->getTimeout($storeId));
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Accept', 'application/json');
            $requestBody = json_encode($payload, JSON_THROW_ON_ERROR);
            $curl->post($this->config->getViesEndpoint($storeId), $requestBody);

            $status = $curl->getStatus();
            $body = (string) $curl->getBody();

            if ($status < 200 || $status >= 300) {
                $this->logger->warning(sprintf('VIES returned HTTP %d for %s%s', $status, $country, $number));

                return $this->buildResult($country, $number, ValidationResultInterface::STATUS_UNAVAILABLE, 'HTTP ' . $status);
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                return $this->buildResult($country, $number, ValidationResultInterface::STATUS_UNAVAILABLE, 'Malformed VIES response');
            }

            $isValid = (bool) ($data['isValid'] ?? $data['valid'] ?? false);
            $status = $isValid ? ValidationResultInterface::STATUS_VALID : ValidationResultInterface::STATUS_INVALID;

            return $this->resultFactory->create(['data' => [
                'country_code' => $country,
                'vat_number' => $number,
                'status' => $status,
                'source' => ValidationResultInterface::SOURCE_VIES,
                'name' => $data['name'] ?? null,
                'address' => $data['address'] ?? null,
                'request_identifier' => $data['requestIdentifier'] ?? null,
                'message' => $data['userError'] ?? null,
                'request_payload' => $requestBody,
                'response_payload' => $body,
            ]]);
        } catch (\Throwable $e) {
            $this->logger->error('VIES validate error: ' . $e->getMessage());

            return $this->buildResult($country, $number, ValidationResultInterface::STATUS_UNAVAILABLE, $e->getMessage());
        }
    }

    private function buildResult(string $country, string $number, string $status, ?string $message): ValidationResult
    {
        return $this->resultFactory->create(['data' => [
            'country_code' => $country,
            'vat_number' => $number,
            'status' => $status,
            'source' => ValidationResultInterface::SOURCE_VIES,
            'message' => $message,
        ]]);
    }
}
