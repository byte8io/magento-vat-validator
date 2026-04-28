<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Byte8\VatValidator\Model\Client\HmrcClient;
use Byte8\VatValidator\Model\Client\UidCheClient;
use Byte8\VatValidator\Model\Client\ViesClient;

/**
 * Cheap, network-free pre-check for obviously malformed VAT numbers.
 *
 * The checkout observer calls this before falling back to cache lookup +
 * async revalidation, so format errors (e.g. a 10-digit GB number) are
 * caught synchronously and the customer gets immediate feedback rather
 * than discovering days later that their order didn't qualify for
 * reverse-charge.
 */
class FormatValidator
{
    public function __construct(
        private readonly ValidationResultFactory $resultFactory
    ) {
    }

    /**
     * Returns null when the format looks plausible (caller should proceed
     * with cache / async revalidation). Returns a STATUS_INVALID result
     * when the format is unambiguously wrong.
     */
    public function check(string $countryCode, string $vatNumber): ?ValidationResultInterface
    {
        $country = strtoupper($countryCode);
        $digits = preg_replace('/[^0-9]/', '', $vatNumber) ?? '';
        $alnum = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vatNumber) ?? '');

        $message = null;
        $source = ValidationResultInterface::SOURCE_NONE;

        if ($country === 'GB' || $country === 'XI') {
            $source = ValidationResultInterface::SOURCE_HMRC;
            $length = strlen($digits);
            if ($length !== 9 && $length !== 12) {
                $message = sprintf('UK VAT must be 9 or 12 digits (got %d).', $length);
            }
        } elseif ($country === 'CH') {
            $source = ValidationResultInterface::SOURCE_UID_CHE;
            if (strlen($digits) !== 9) {
                $message = 'Swiss UID must be 9 digits (CHE-123.456.789).';
            }
        } elseif (in_array($country, ViesClient::SUPPORTED_COUNTRIES, true)) {
            $source = ValidationResultInterface::SOURCE_VIES;
            // VIES per-country rules vary considerably; reject only obviously
            // wrong inputs (too short / too long) to avoid false positives.
            $length = strlen($alnum);
            if ($length < 4 || $length > 14) {
                $message = sprintf('EU VAT must be between 4 and 14 characters (got %d).', $length);
            }
        }

        if ($message === null) {
            return null;
        }

        return $this->resultFactory->create(['data' => [
            'country_code' => $country,
            'vat_number' => $alnum,
            'status' => ValidationResultInterface::STATUS_INVALID,
            'source' => $source,
            'message' => $message,
        ]]);
    }
}
