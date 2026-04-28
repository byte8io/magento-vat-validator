<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Block\Form;

use Byte8\VatValidator\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Renders the x-magento-init script that bootstraps the live Validate
 * button on the registration / account-edit form. Carries the merchant's
 * configured requester country so the JS can derive the buyer's country
 * when the entered taxvat doesn't include a 2-letter prefix.
 */
class RegisterVatInit extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->validateOnCustomerSave();
    }

    public function getFallbackCountry(): string
    {
        return $this->config->getRequesterCountry() ?? '';
    }
}
