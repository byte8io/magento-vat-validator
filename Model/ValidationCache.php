<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;

/**
 * In-memory cache so a single request never hits VIES/HMRC twice for the same input.
 * Guards against multi-observer recursion (customer + address + quote all saving during checkout).
 */
class ValidationCache
{
    /** @var array<string, ValidationResultInterface> */
    private array $cache = [];

    public function get(string $countryCode, string $vatNumber): ?ValidationResultInterface
    {
        return $this->cache[$this->key($countryCode, $vatNumber)] ?? null;
    }

    public function put(ValidationResultInterface $result): void
    {
        $this->cache[$this->key($result->getCountryCode(), $result->getVatNumber())] = $result;
    }

    private function key(string $countryCode, string $vatNumber): string
    {
        return strtoupper($countryCode) . ':' . strtoupper($vatNumber);
    }
}
