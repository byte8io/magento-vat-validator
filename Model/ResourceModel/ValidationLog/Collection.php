<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model\ResourceModel\ValidationLog;

use Byte8\VatValidator\Model\ResourceModel\ValidationLog as ValidationLogResource;
use Byte8\VatValidator\Model\ValidationLog;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(ValidationLog::class, ValidationLogResource::class);
    }
}
