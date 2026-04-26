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

    public function getCountryCode(): string;

    public function getVatNumber(): string;

    public function getStatus(): string;

    public function getSource(): string;

    public function getName(): ?string;

    public function getAddress(): ?string;

    public function getRequestIdentifier(): ?string;

    public function getMessage(): ?string;
}
