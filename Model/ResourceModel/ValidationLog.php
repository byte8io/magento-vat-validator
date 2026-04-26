<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ValidationLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('byte8_vat_validator_log', 'entity_id');
    }
}
