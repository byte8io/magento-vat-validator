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

    public function enrichLatest(string $countryCode, string $vatNumber, ?int $customerId, ?string $customerEmail): int
    {
        if ($customerId === null && ($customerEmail === null || $customerEmail === '')) {
            return 0;
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getMainTable(), 'entity_id')
            ->where('country_code = ?', strtoupper($countryCode))
            ->where('vat_number = ?', strtoupper($vatNumber))
            ->order('requested_at DESC')
            ->limit(1);

        $entityId = $connection->fetchOne($select);
        if ($entityId === false || $entityId === null) {
            return 0;
        }

        $bind = [];
        $where = ['entity_id = ?' => (int) $entityId];

        if ($customerId !== null) {
            $bind['customer_id'] = $customerId;
            $where['customer_id IS NULL'] = null;
        }
        if ($customerEmail !== null && $customerEmail !== '') {
            $bind['customer_email'] = $customerEmail;
        }
        if ($bind === []) {
            return 0;
        }

        // Build a WHERE that updates only when the target columns are NULL,
        // so we never overwrite a value that was already correctly attached
        // (e.g. by an interactive request that ran before the consumer).
        $conditions = ['entity_id = ?'];
        $params = [(int) $entityId];
        $nullable = [];
        if ($customerId !== null) {
            $nullable[] = 'customer_id IS NULL';
        }
        if ($customerEmail !== null && $customerEmail !== '') {
            $nullable[] = 'customer_email IS NULL';
        }
        $whereSql = '(' . implode(' OR ', $nullable) . ')';
        $sql = sprintf(
            'UPDATE %s SET %s WHERE entity_id = ? AND %s',
            $connection->quoteIdentifier($this->resource->getMainTable()),
            implode(', ', array_map(
                static fn(string $col): string => $col . ' = COALESCE(' . $col . ', ?)',
                array_keys($bind)
            )),
            $whereSql
        );

        $values = array_merge(array_values($bind), [(int) $entityId]);

        return (int) $connection->query($sql, $values)->rowCount();
    }

    public function getLatestFresh(string $countryCode, string $vatNumber, int $maxAgeSeconds): ?ValidationLogInterface
    {
        $connection = $this->resource->getConnection();
        $threshold = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-' . max(0, $maxAgeSeconds) . ' seconds')
            ->format('Y-m-d H:i:s');

        $select = $connection->select()
            ->from($this->resource->getMainTable(), 'entity_id')
            ->where('country_code = ?', strtoupper($countryCode))
            ->where('vat_number = ?', strtoupper($vatNumber))
            ->where('requested_at >= ?', $threshold)
            ->order('requested_at DESC')
            ->limit(1);

        $entityId = $connection->fetchOne($select);
        if ($entityId === false || $entityId === null) {
            return null;
        }

        try {
            return $this->getById((int) $entityId);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
