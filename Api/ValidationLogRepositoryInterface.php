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
}
