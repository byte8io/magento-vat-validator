<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Byte8\VatValidator\Api\Data\RevalidationRequestInterface;
use Magento\Framework\DataObject;

/**
 * Plain DTO published to byte8.vat.revalidate. DataObject base gives us
 * Magento's standard JSON-friendly hydration so the message survives the
 * default queue serializer round-trip.
 */
class RevalidationRequest extends DataObject implements RevalidationRequestInterface
{
    public function getCountryCode(): string
    {
        return (string) $this->getData('country_code');
    }

    public function setCountryCode(string $countryCode): self
    {
        return $this->setData('country_code', $countryCode);
    }

    public function getVatNumber(): string
    {
        return (string) $this->getData('vat_number');
    }

    public function setVatNumber(string $vatNumber): self
    {
        return $this->setData('vat_number', $vatNumber);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function setStoreId(?int $storeId): self
    {
        return $this->setData('store_id', $storeId);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function setCustomerId(?int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    public function getQuoteId(): ?int
    {
        $value = $this->getData('quote_id');

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function setQuoteId(?int $quoteId): self
    {
        return $this->setData('quote_id', $quoteId);
    }
}
