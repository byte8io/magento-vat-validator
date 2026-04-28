<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Ui\Component\Listing\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Adds a "VAT Status" column to the admin sales order grid. The grid table
 * (sales_order_grid) does not store vat_is_valid, so we batch-fetch the
 * billing address's flag for the visible page in one query and stitch the
 * result back onto the rows. N+1 avoided.
 */
class OrderVatStatus extends Column
{
    public const VALUE_VALID = 'valid';
    public const VALUE_INVALID = 'invalid';
    public const VALUE_UNVERIFIED = 'unverified';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ResourceConnection $resource,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || $dataSource['data']['items'] === []) {
            return $dataSource;
        }

        $orderIds = array_filter(array_map(
            static fn(array $row): ?int => isset($row['entity_id']) ? (int) $row['entity_id'] : null,
            $dataSource['data']['items']
        ));

        if ($orderIds === []) {
            return $dataSource;
        }

        $statusByOrderId = $this->fetchVatStatuses($orderIds);
        $columnName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$row) {
            $orderId = isset($row['entity_id']) ? (int) $row['entity_id'] : 0;
            $row[$columnName] = $statusByOrderId[$orderId] ?? self::VALUE_UNVERIFIED;
        }
        unset($row);

        return $dataSource;
    }

    /**
     * @param int[] $orderIds
     * @return array<int,string>
     */
    private function fetchVatStatuses(array $orderIds): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['a' => $this->resource->getTableName('sales_order_address')],
                ['parent_id', 'vat_id', 'vat_is_valid']
            )
            ->where('a.parent_id IN (?)', $orderIds)
            ->where('a.address_type = ?', 'billing');

        $rows = $connection->fetchAll($select);
        $byOrderId = [];

        foreach ($rows as $row) {
            $orderId = (int) $row['parent_id'];
            $vatId = (string) ($row['vat_id'] ?? '');

            if ($vatId === '') {
                $byOrderId[$orderId] = self::VALUE_UNVERIFIED;

                continue;
            }

            $vatIsValid = $row['vat_is_valid'];
            if ($vatIsValid === null || $vatIsValid === '') {
                $byOrderId[$orderId] = self::VALUE_UNVERIFIED;

                continue;
            }

            $byOrderId[$orderId] = ((int) $vatIsValid) === 1 ? self::VALUE_VALID : self::VALUE_INVALID;
        }

        return $byOrderId;
    }
}
