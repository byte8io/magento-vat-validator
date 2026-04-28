<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Api\Data;

interface ValidationResultInterface
{
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_SKIPPED = 'skipped';

    public const SOURCE_VIES = 'vies';
    public const SOURCE_HMRC = 'hmrc';
    public const SOURCE_UID_CHE = 'uid_che';
    public const SOURCE_NONE = 'none';

    /**
     * @return string
     */
    public function getCountryCode(): string;

    /**
     * @return string
     */
    public function getVatNumber(): string;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @return string
     */
    public function getSource(): string;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @return string|null
     */
    public function getAddress(): ?string;

    /**
     * @return string|null
     */
    public function getRequestIdentifier(): ?string;

    /**
     * @return string|null
     */
    public function getMessage(): ?string;
}
