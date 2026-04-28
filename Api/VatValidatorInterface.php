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
     * Validate a VAT number against VIES (EU), HMRC (UK), or UID-CHE (Switzerland).
     * Always synchronous — every call hits the upstream service. Use this from
     * the customer-save / address-form path where the buyer is waiting for
     * immediate "✓ valid" feedback.
     *
     * @param string $countryCode 2-letter ISO country code (e.g. DE, FR, GB)
     * @param string $vatNumber VAT number without the country prefix — the prefix is normalised away if included
     * @return ValidationResultInterface
     */
    public function validate(string $countryCode, string $vatNumber): ValidationResultInterface;

    /**
     * Cache-aware lookup. Returns a fresh persisted result if one exists within
     * cache_ttl_hours; otherwise enqueues an asynchronous revalidation and
     * returns a `skipped` placeholder so the caller can render "checking…".
     * Use this from latency-sensitive paths (checkout JS, server-side checkout
     * observers) where blocking on a third-party API is unacceptable.
     *
     * @param string $countryCode 2-letter ISO country code
     * @param string $vatNumber VAT number without the country prefix
     * @return ValidationResultInterface
     */
    public function validateCached(string $countryCode, string $vatNumber): ValidationResultInterface;
}
