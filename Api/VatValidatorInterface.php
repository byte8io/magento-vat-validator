<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Api;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;

interface VatValidatorInterface
{
    /**
     * Validate a VAT number against VIES (EU) or HMRC (UK) based on its country prefix.
     *
     * @param string $countryCode 2-letter ISO country code (e.g. DE, FR, GB)
     * @param string $vatNumber VAT number without the country prefix — the prefix is normalised away if included
     * @return ValidationResultInterface
     */
    public function validate(string $countryCode, string $vatNumber): ValidationResultInterface;
}
