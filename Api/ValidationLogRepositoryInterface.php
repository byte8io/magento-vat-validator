<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Api;

use Byte8\VatValidator\Api\Data\ValidationLogInterface;
use Byte8\VatValidator\Api\Data\ValidationLogSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface ValidationLogRepositoryInterface
{
    /**
     * @throws CouldNotSaveException
     */
    public function save(ValidationLogInterface $entry): ValidationLogInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): ValidationLogInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): ValidationLogSearchResultsInterface;

    public function deleteOlderThan(string $isoTimestamp): int;

    /**
     * Returns the most recent persisted log entry for (countryCode, vatNumber)
     * whose requested_at is no older than $maxAgeSeconds. Used as a non-blocking
     * cache lookup during checkout — see Observer\ValidateQuoteAddress.
     */
    public function getLatestFresh(string $countryCode, string $vatNumber, int $maxAgeSeconds): ?ValidationLogInterface;

    /**
     * Backfill customer_id / customer_email on the most recent log row for
     * (countryCode, vatNumber) when those fields are NULL. Used by the queue
     * consumer to attach guest-checkout context that was not available at the
     * moment the row was written. Returns the number of rows updated (0 or 1).
     */
    public function enrichLatest(string $countryCode, string $vatNumber, ?int $customerId, ?string $customerEmail): int;
}
