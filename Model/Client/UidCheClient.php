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

/**
 * Swiss UID-Register validator (Bundesamt für Statistik).
 *
 * Uses a hand-rolled SOAP envelope against the public PublicServices
 * endpoint, deliberately avoiding ext-soap so we keep the module's
 * "no SOAP dependency" architectural promise.
 *
 * Switzerland's UID-Register identifies businesses; merchants typically
 * want to additionally confirm VAT registration. This client returns
 * STATUS_VALID only when the organisation exists, is active, AND is
 * VAT-registered — matching what a German/UK merchant means by "is this
 * a valid Swiss B2B counterparty for reverse-charge purposes".
 */
class UidCheClient
{
    private const SOAP_NAMESPACE = 'http://www.uid.admin.ch/xmlns/uid-wse';
    private const SOAP_ACTION = 'http://www.uid.admin.ch/xmlns/uid-wse/IPublicServicesV5/GetByUID';

    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly ValidationResultFactory $resultFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function supports(string $countryCode): bool
    {
        return strtoupper($countryCode) === 'CH';
    }

    public function validate(string $countryCode, string $vatNumber, ?int $storeId = null): ValidationResult
    {
        $country = strtoupper($countryCode);
        $digits = preg_replace('/[^0-9]/', '', $vatNumber) ?? '';

        if (strlen($digits) !== 9) {
            return $this->buildResult($country, $vatNumber, ValidationResultInterface::STATUS_INVALID, 'Swiss UID must be 9 digits (CHE-123.456.789)');
        }

        $envelope = $this->buildEnvelope($digits);

        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($this->config->getTimeout($storeId));
            $curl->setOption(CURLOPT_CONNECTTIMEOUT, $this->config->getConnectTimeout($storeId));
            $curl->addHeader('Content-Type', 'text/xml; charset=utf-8');
            $curl->addHeader('SOAPAction', '"' . self::SOAP_ACTION . '"');
            $curl->post($this->config->getUidCheEndpoint($storeId), $envelope);

            $status = $curl->getStatus();
            $body = (string) $curl->getBody();

            if ($status === 404) {
                return $this->buildResult($country, $digits, ValidationResultInterface::STATUS_INVALID, 'Swiss UID-Register: not found', $envelope, $body);
            }

            if ($status < 200 || $status >= 300) {
                $this->logger->warning(sprintf('UID-CHE returned HTTP %d for CHE-%s', $status, $digits));

                return $this->buildResult($country, $digits, ValidationResultInterface::STATUS_UNAVAILABLE, 'HTTP ' . $status, $envelope, $body);
            }

            return $this->parseResponse($country, $digits, $envelope, $body);
        } catch (\Throwable $e) {
            $this->logger->error('UID-CHE validate error: ' . $e->getMessage());

            return $this->buildResult($country, $digits, ValidationResultInterface::STATUS_UNAVAILABLE, $e->getMessage());
        }
    }

    private function buildEnvelope(string $digits): string
    {
        $ns = self::SOAP_NAMESPACE;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:tns="{$ns}">
  <soap:Body>
    <tns:GetByUID>
      <tns:uid>
        <tns:uidOrganisationIdCategorie>CHE</tns:uidOrganisationIdCategorie>
        <tns:uidOrganisationId>{$digits}</tns:uidOrganisationId>
      </tns:uid>
    </tns:GetByUID>
  </soap:Body>
</soap:Envelope>
XML;
    }

    private function parseResponse(string $country, string $digits, string $request, string $body): ValidationResult
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body);
            if ($xml === false) {
                return $this->buildResult($country, $digits, ValidationResultInterface::STATUS_UNAVAILABLE, 'Malformed UID-CHE response', $request, $body);
            }

            $xml->registerXPathNamespace('uid', self::SOAP_NAMESPACE);

            $statusNodes = $xml->xpath('//uid:status');
            $vatNodes = $xml->xpath('//uid:vatRegisteredAndActive');
            $nameNodes = $xml->xpath('//uid:organisationName | //uid:name');
            $townNodes = $xml->xpath('//uid:address//uid:town | //uid:address/uid:Town');
            $streetNodes = $xml->xpath('//uid:address//uid:street | //uid:address/uid:Street');

            $orgStatus = $statusNodes ? strtoupper((string) $statusNodes[0]) : '';
            $vatActive = $vatNodes ? in_array(strtolower((string) $vatNodes[0]), ['true', '1'], true) : false;
            $name = $nameNodes ? (string) $nameNodes[0] : null;
            $address = $this->joinNonEmpty([
                $streetNodes ? (string) $streetNodes[0] : null,
                $townNodes ? (string) $townNodes[0] : null,
                'CH',
            ]);

            $isActive = $orgStatus === '' || $orgStatus === 'ACTIVE';
            $status = ($isActive && $vatActive)
                ? ValidationResultInterface::STATUS_VALID
                : ValidationResultInterface::STATUS_INVALID;

            $message = $isActive
                ? ($vatActive ? null : 'Swiss UID exists but is not VAT-registered (no MWST)')
                : 'Swiss UID-Register reports the organisation as inactive';

            return $this->resultFactory->create(['data' => [
                'country_code' => $country,
                'vat_number' => $digits,
                'status' => $status,
                'source' => ValidationResultInterface::SOURCE_UID_CHE,
                'name' => $name,
                'address' => $address,
                'request_identifier' => 'CHE-' . $digits,
                'message' => $message,
                'request_payload' => $request,
                'response_payload' => $body,
            ]]);
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    private function joinNonEmpty(array $parts): ?string
    {
        $filtered = array_values(array_filter($parts, static fn($v): bool => $v !== null && $v !== ''));

        return $filtered ? implode(', ', $filtered) : null;
    }

    private function buildResult(string $country, string $number, string $status, ?string $message, ?string $request = null, ?string $response = null): ValidationResult
    {
        return $this->resultFactory->create(['data' => [
            'country_code' => $country,
            'vat_number' => $number,
            'status' => $status,
            'source' => ValidationResultInterface::SOURCE_UID_CHE,
            'message' => $message,
            'request_payload' => $request,
            'response_payload' => $response,
        ]]);
    }
}
