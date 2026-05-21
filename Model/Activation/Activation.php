<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model\Activation;

/**
 * Type-hint anchor for VAT Validator's activation gate. The real
 * behaviour lives in `byte8/module-activation`. This subclass exists so
 * Magento DI can bind product-specific arguments (productId,
 * configPathPrefix) without conflicting with other products' DI for
 * the same base class.
 *
 * See module-vat-validator/etc/di.xml for the wiring; the shared module
 * for the implementation.
 */
class Activation extends \Byte8\Activation\Model\Activation
{
}
