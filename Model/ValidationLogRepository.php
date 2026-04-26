<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model;

use Byte8\VatValidator\Api\Data\ValidationLogInterface;
use Byte8\VatValidator\Api\Data\ValidationLogSearchResultsInterface;
use Byte8\VatValidator\Api\Data\ValidationLogSearchResultsInterfaceFactory;
use Byte8\VatValidator\Api\ValidationLogRepositoryInterface;
use Byte8\VatValidator\Model\ResourceModel\ValidationLog as ValidationLogResource;
use Byte8\VatValidator\Model\ResourceModel\ValidationLog\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class ValidationLogRepository implements ValidationLogRepositoryInterface
{
    public function __construct(
        private readonly ValidationLogResource $resource,
        private readonly ValidationLogFactory $factory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ValidationLogSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function save(ValidationLogInterface $entry): ValidationLogInterface
    {
        try {
            $this->resource->save($entry);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Unable to persist VAT validation log entry: %1', $e->getMessage()), $e);
        }

        return $entry;
    }

    public function getById(int $entityId): ValidationLogInterface
    {
        /** @var ValidationLog $entry */
        $entry = $this->factory->create();
        $this->resource->load($entry, $entityId);

        if ($entry->getEntityId() === null) {
            throw new NoSuchEntityException(__('VAT validation log entry %1 not found.', $entityId));
        }

        return $entry;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): ValidationLogSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var ValidationLogSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function deleteOlderThan(string $isoTimestamp): int
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->delete(
            $this->resource->getMainTable(),
            ['requested_at < ?' => $isoTimestamp]
        );
    }
}
