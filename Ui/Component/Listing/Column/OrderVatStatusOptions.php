<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class OrderVatStatusOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => OrderVatStatus::VALUE_VALID, 'label' => __('Valid')],
            ['value' => OrderVatStatus::VALUE_INVALID, 'label' => __('Invalid')],
            ['value' => OrderVatStatus::VALUE_UNVERIFIED, 'label' => __('Unverified')],
        ];
    }
}
