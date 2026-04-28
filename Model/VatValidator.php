<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Byte8\VatValidator\Api\Data\ValidationLogInterface;
use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Byte8\VatValidator\Api\ValidationLogRepositoryInterface;
use Byte8\VatValidator\Api\VatValidatorInterface;
use Byte8\VatValidator\Model\Client\HmrcClient;
use Byte8\VatValidator\Model\Client\UidCheClient;
use Byte8\VatValidator\Model\Client\ViesClient;
use Byte8\VatValidator\Model\Queue\RevalidationPublisher;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;

class VatValidator implements VatValidatorInterface
{
    public const EVENT_VALIDATED = 'byte8_vat_validator_validated';

    public function __construct(
        private readonly Config $config,
        private readonly ViesClient $viesClient,
        private readonly HmrcClient $hmrcClient,
        private readonly UidCheClient $uidCheClient,
        private readonly ValidationResultFactory $resultFactory,
        private readonly ValidationCache $cache,
        private readonly EventManagerInterface $eventManager,
        private readonly ValidationLogRepositoryInterface $logRepository,
        private readonly RevalidationPublisher $publisher
    ) {
    }

    public function validate(string $countryCode, string $vatNumber): ValidationResultInterface
    {
        [$country, $number] = $this->normalise($countryCode, $vatNumber);

        if (!$this->config->isEnabled()) {
            return $this->skipped($country, $number, 'Module disabled');
        }

        if ($country === '' || $number === '') {
            return $this->skipped($country, $number, 'Country or VAT number missing');
        }

        $cached = $this->cache->get($country, $number);
        if ($cached !== null) {
            return $cached;
        }

        if ($this->hmrcClient->supports($country) && $this->config->isHmrcEnabled()) {
            $result = $this->hmrcClient->validate($country, $number);
        } elseif ($this->uidCheClient->supports($country) && $this->config->isUidCheEnabled()) {
            $result = $this->uidCheClient->validate($country, $number);
        } elseif ($this->viesClient->supports($country) && $this->config->isViesEnabled()) {
            $result = $this->viesClient->validate($country, $number);
        } else {
            $result = $this->skipped($country, $number, sprintf('No validator available for country "%s"', $country));
        }

        $this->cache->put($result);
        $this->eventManager->dispatch(self::EVENT_VALIDATED, ['result' => $result]);

        return $result;
    }

    public function validateCached(string $countryCode, string $vatNumber): ValidationResultInterface
    {
        [$country, $number] = $this->normalise($countryCode, $vatNumber);

        if (!$this->config->isEnabled()) {
            return $this->skipped($country, $number, 'Module disabled');
        }

        if ($country === '' || $number === '') {
            return $this->skipped($country, $number, 'Country or VAT number missing');
        }

        $cached = $this->logRepository->getLatestFresh($country, $number, $this->config->getCacheTtlSeconds());
        if ($cached !== null) {
            return $this->resultFromLog($cached);
        }

        $this->publisher->publish($country, $number);

        return $this->skipped($country, $number, 'Revalidation queued');
    }

    private function resultFromLog(ValidationLogInterface $log): ValidationResultInterface
    {
        return $this->resultFactory->create(['data' => [
            'country_code' => $log->getCountryCode(),
            'vat_number' => $log->getVatNumber(),
            'status' => $log->getStatus(),
            'source' => $log->getSource(),
            'name' => $log->getCompanyName(),
            'address' => $log->getCompanyAddress(),
            'request_identifier' => $log->getRequestIdentifier(),
        ]]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalise(string $countryCode, string $vatNumber): array
    {
        $country = strtoupper(trim($countryCode));
        $number = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vatNumber) ?? '');

        // Swiss UID format is CHE-123.456.789 — 3-letter prefix maps to ISO CH
        if (str_starts_with($number, 'CHE')) {
            $country = 'CH';
            $number = substr($number, 3);
        } elseif ($country === '' && strlen($number) >= 2 && ctype_alpha(substr($number, 0, 2))) {
            $country = substr($number, 0, 2);
            $number = substr($number, 2);
        } elseif ($country !== '' && str_starts_with($number, $country)) {
            $number = substr($number, strlen($country));
        }

        if ($country === 'GR') {
            $country = 'EL';
        }

        return [$country, $number];
    }

    private function skipped(string $country, string $number, string $reason): ValidationResultInterface
    {
        return $this->resultFactory->create(['data' => [
            'country_code' => $country,
            'vat_number' => $number,
            'status' => ValidationResultInterface::STATUS_SKIPPED,
            'source' => ValidationResultInterface::SOURCE_NONE,
            'message' => $reason,
        ]]);
    }
}
