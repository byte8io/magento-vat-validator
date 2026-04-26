<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Magento\Framework\DataObject;

class ValidationResult extends DataObject implements ValidationResultInterface
{
    public function getCountryCode(): string
    {
        return (string) $this->_getData('country_code');
    }

    public function getVatNumber(): string
    {
        return (string) $this->_getData('vat_number');
    }

    public function getStatus(): string
    {
        return (string) $this->_getData('status');
    }

    public function getSource(): string
    {
        return (string) ($this->_getData('source') ?: self::SOURCE_NONE);
    }

    public function getName(): ?string
    {
        $value = $this->_getData('name');

        return $value !== null ? (string) $value : null;
    }

    public function getAddress(): ?string
    {
        $value = $this->_getData('address');

        return $value !== null ? (string) $value : null;
    }

    public function getRequestIdentifier(): ?string
    {
        $value = $this->_getData('request_identifier');

        return $value !== null ? (string) $value : null;
    }

    public function getMessage(): ?string
    {
        $value = $this->_getData('message');

        return $value !== null ? (string) $value : null;
    }

    public function isValid(): bool
    {
        return $this->getStatus() === self::STATUS_VALID;
    }

    public function isInvalid(): bool
    {
        return $this->getStatus() === self::STATUS_INVALID;
    }
}
