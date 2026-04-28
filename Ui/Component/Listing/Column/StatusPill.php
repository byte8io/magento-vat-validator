<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Ui\Component\Listing\Column;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders the validation log "status" cell as a colour-coded pill —
 * green for valid, red for invalid. The grid filter still uses the raw
 * status value (StatusOptions) so filtering / sorting are unaffected.
 */
class StatusPill extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || $dataSource['data']['items'] === []) {
            return $dataSource;
        }

        $columnName = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$row) {
            $status = isset($row[$columnName]) ? (string) $row[$columnName] : '';
            $row[$columnName] = $this->renderPill($status);
        }
        unset($row);

        return $dataSource;
    }

    private function renderPill(string $status): string
    {
        $label = match ($status) {
            ValidationResultInterface::STATUS_VALID       => 'Valid',
            ValidationResultInterface::STATUS_INVALID     => 'Invalid',
            ValidationResultInterface::STATUS_UNAVAILABLE => 'Unavailable',
            ValidationResultInterface::STATUS_SKIPPED     => 'Skipped',
            ''                                            => '—',
            default                                       => ucfirst($status),
        };

        $cssClass = match ($status) {
            ValidationResultInterface::STATUS_VALID       => 'byte8-vat-pill byte8-vat-pill--valid',
            ValidationResultInterface::STATUS_INVALID     => 'byte8-vat-pill byte8-vat-pill--invalid',
            ValidationResultInterface::STATUS_UNAVAILABLE => 'byte8-vat-pill byte8-vat-pill--unavailable',
            default                                       => 'byte8-vat-pill byte8-vat-pill--neutral',
        };

        return sprintf(
            '<span class="%s">%s</span>',
            htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );
    }
}
