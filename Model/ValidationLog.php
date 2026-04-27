<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Byte8\VatValidator\Api\Data\ValidationLogInterface;
use Magento\Framework\Model\AbstractModel;

class ValidationLog extends AbstractModel implements ValidationLogInterface
{
    protected function _construct(): void
    {
        $this->_init(\Byte8\VatValidator\Model\ResourceModel\ValidationLog::class);
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);

        return $value === null ? null : (int) $value;
    }

    public function setEntityId($entityId)
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData(self::CUSTOMER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $customerId): self
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getCustomerEmail(): ?string
    {
        $value = $this->getData(self::CUSTOMER_EMAIL);

        return $value === null ? null : (string) $value;
    }

    public function setCustomerEmail(?string $email): self
    {
        return $this->setData(self::CUSTOMER_EMAIL, $email);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getCountryCode(): string
    {
        return (string) $this->getData(self::COUNTRY_CODE);
    }

    public function setCountryCode(string $countryCode): self
    {
        return $this->setData(self::COUNTRY_CODE, $countryCode);
    }

    public function getVatNumber(): string
    {
        return (string) $this->getData(self::VAT_NUMBER);
    }

    public function setVatNumber(string $vatNumber): self
    {
        return $this->setData(self::VAT_NUMBER, $vatNumber);
    }

    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getSource(): string
    {
        return (string) $this->getData(self::SOURCE);
    }

    public function setSource(string $source): self
    {
        return $this->setData(self::SOURCE, $source);
    }

    public function getRequestIdentifier(): ?string
    {
        $value = $this->getData(self::REQUEST_IDENTIFIER);

        return $value === null ? null : (string) $value;
    }

    public function setRequestIdentifier(?string $identifier): self
    {
        return $this->setData(self::REQUEST_IDENTIFIER, $identifier);
    }

    public function getCompanyName(): ?string
    {
        $value = $this->getData(self::COMPANY_NAME);

        return $value === null ? null : (string) $value;
    }

    public function setCompanyName(?string $name): self
    {
        return $this->setData(self::COMPANY_NAME, $name);
    }

    public function getCompanyAddress(): ?string
    {
        $value = $this->getData(self::COMPANY_ADDRESS);

        return $value === null ? null : (string) $value;
    }

    public function setCompanyAddress(?string $address): self
    {
        return $this->setData(self::COMPANY_ADDRESS, $address);
    }

    public function getRequestPayload(): ?string
    {
        $value = $this->getData(self::REQUEST_PAYLOAD);

        return $value === null ? null : (string) $value;
    }

    public function setRequestPayload(?string $payload): self
    {
        return $this->setData(self::REQUEST_PAYLOAD, $payload);
    }

    public function getResponsePayload(): ?string
    {
        $value = $this->getData(self::RESPONSE_PAYLOAD);

        return $value === null ? null : (string) $value;
    }

    public function setResponsePayload(?string $payload): self
    {
        return $this->setData(self::RESPONSE_PAYLOAD, $payload);
    }

    public function getRequestedAt(): ?string
    {
        $value = $this->getData(self::REQUESTED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setRequestedAt(?string $requestedAt): self
    {
        return $this->setData(self::REQUESTED_AT, $requestedAt);
    }
}
