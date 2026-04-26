<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Ui\Component\Listing\Column;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Magento\Framework\Data\OptionSourceInterface;

class StatusOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => ValidationResultInterface::STATUS_VALID, 'label' => __('Valid')],
            ['value' => ValidationResultInterface::STATUS_INVALID, 'label' => __('Invalid')],
        ];
    }
}
