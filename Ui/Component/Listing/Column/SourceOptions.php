<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Ui\Component\Listing\Column;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Magento\Framework\Data\OptionSourceInterface;

class SourceOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => ValidationResultInterface::SOURCE_VIES, 'label' => __('VIES (EU)')],
            ['value' => ValidationResultInterface::SOURCE_HMRC, 'label' => __('HMRC (UK)')],
            ['value' => ValidationResultInterface::SOURCE_UID_CHE, 'label' => __('UID-Register (CH)')],
        ];
    }
}
