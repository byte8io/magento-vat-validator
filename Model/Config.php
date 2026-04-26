<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_ENABLED = 'byte8_vat_validator/general/enabled';
    public const XML_VALIDATE_ON_CUSTOMER_SAVE = 'byte8_vat_validator/general/validate_on_customer_save';
    public const XML_VALIDATE_ON_CHECKOUT = 'byte8_vat_validator/general/validate_on_checkout';
    public const XML_TIMEOUT = 'byte8_vat_validator/general/timeout';
    public const XML_REQUESTER_COUNTRY = 'byte8_vat_validator/general/requester_country';
    public const XML_REQUESTER_VAT = 'byte8_vat_validator/general/requester_vat_number';

    public const XML_VIES_ENABLED = 'byte8_vat_validator/vies/enabled';
    public const XML_VIES_ENDPOINT = 'byte8_vat_validator/vies/endpoint';

    public const XML_HMRC_ENABLED = 'byte8_vat_validator/hmrc/enabled';
    public const XML_HMRC_ENDPOINT = 'byte8_vat_validator/hmrc/endpoint';

    public const XML_UID_CHE_ENABLED = 'byte8_vat_validator/uid_che/enabled';
    public const XML_UID_CHE_ENDPOINT = 'byte8_vat_validator/uid_che/endpoint';

    public const XML_AUTO_ASSIGN = 'byte8_vat_validator/groups/auto_assign';
    public const XML_DOMESTIC_GROUP = 'byte8_vat_validator/groups/domestic_group_id';
    public const XML_INTRA_EU_GROUP = 'byte8_vat_validator/groups/intra_eu_valid_group_id';
    public const XML_INVALID_GROUP = 'byte8_vat_validator/groups/invalid_group_id';

    public const XML_LOG_ENABLED = 'byte8_vat_validator/log/enabled';
    public const XML_LOG_RETENTION_YEARS = 'byte8_vat_validator/log/retention_years';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_ENABLED, $storeId);
    }

    public function validateOnCustomerSave(?int $storeId = null): bool
    {
        return $this->flag(self::XML_VALIDATE_ON_CUSTOMER_SAVE, $storeId);
    }

    public function validateOnCheckout(?int $storeId = null): bool
    {
        return $this->flag(self::XML_VALIDATE_ON_CHECKOUT, $storeId);
    }

    public function getTimeout(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_TIMEOUT, ScopeInterface::SCOPE_STORE, $storeId);

        return $value > 0 ? $value : 5;
    }

    public function getRequesterCountry(?int $storeId = null): ?string
    {
        $value = trim((string) $this->scopeConfig->getValue(self::XML_REQUESTER_COUNTRY, ScopeInterface::SCOPE_STORE, $storeId));

        return $value !== '' ? strtoupper($value) : null;
    }

    public function getRequesterVatNumber(?int $storeId = null): ?string
    {
        $value = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $this->scopeConfig->getValue(self::XML_REQUESTER_VAT, ScopeInterface::SCOPE_STORE, $storeId)));

        return $value !== '' ? $value : null;
    }

    public function isViesEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_VIES_ENABLED, $storeId);
    }

    public function getViesEndpoint(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_VIES_ENDPOINT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isHmrcEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_HMRC_ENABLED, $storeId);
    }

    public function getHmrcEndpoint(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_HMRC_ENDPOINT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isUidCheEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_UID_CHE_ENABLED, $storeId);
    }

    public function getUidCheEndpoint(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_UID_CHE_ENDPOINT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function autoAssignGroup(?int $storeId = null): bool
    {
        return $this->flag(self::XML_AUTO_ASSIGN, $storeId);
    }

    public function getDomesticGroupId(?int $storeId = null): ?int
    {
        return $this->intOrNull(self::XML_DOMESTIC_GROUP, $storeId);
    }

    public function getIntraEuGroupId(?int $storeId = null): ?int
    {
        return $this->intOrNull(self::XML_INTRA_EU_GROUP, $storeId);
    }

    public function getInvalidGroupId(?int $storeId = null): ?int
    {
        return $this->intOrNull(self::XML_INVALID_GROUP, $storeId);
    }

    public function isLogEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_LOG_ENABLED, $storeId);
    }

    public function getRetentionYears(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_LOG_RETENTION_YEARS, ScopeInterface::SCOPE_STORE, $storeId);

        return $value > 0 ? $value : 10;
    }

    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function intOrNull(string $path, ?int $storeId): ?int
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return is_numeric($value) ? (int) $value : null;
    }
}
