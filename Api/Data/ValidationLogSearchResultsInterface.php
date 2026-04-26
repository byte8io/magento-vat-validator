<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface ValidationLogSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return ValidationLogInterface[]
     */
    public function getItems();

    /**
     * @param ValidationLogInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
