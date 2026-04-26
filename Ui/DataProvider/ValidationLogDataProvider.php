<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

/**
 * Thin extension of the framework UI data provider so the grid has a stable
 * class to reference and we can grow it with custom filter handling later.
 */
class ValidationLogDataProvider extends DataProvider
{
    public function addFilter(Filter $filter): void
    {
        parent::addFilter($filter);
    }
}
