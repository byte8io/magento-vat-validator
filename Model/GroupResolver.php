<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;

class GroupResolver
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Decide the customer group id to apply given a validation result and buyer country.
     * Returns null when the current group should be left alone (e.g. VIES unavailable).
     */
    public function resolve(ValidationResultInterface $result, ?int $storeId = null): ?int
    {
        if (!$this->config->autoAssignGroup($storeId)) {
            return null;
        }

        $status = $result->getStatus();

        if ($status === ValidationResultInterface::STATUS_UNAVAILABLE
            || $status === ValidationResultInterface::STATUS_SKIPPED) {
            return null;
        }

        if ($status === ValidationResultInterface::STATUS_INVALID) {
            return $this->config->getInvalidGroupId($storeId);
        }

        $requesterCountry = $this->config->getRequesterCountry($storeId);
        $buyerCountry = $result->getCountryCode();

        if ($requesterCountry !== null && $this->isSameCountry($requesterCountry, $buyerCountry)) {
            return $this->config->getDomesticGroupId($storeId);
        }

        return $this->config->getIntraEuGroupId($storeId);
    }

    private function isSameCountry(string $a, string $b): bool
    {
        $map = static fn(string $code): string => $code === 'GR' ? 'EL' : strtoupper($code);

        return $map($a) === $map($b);
    }
}
