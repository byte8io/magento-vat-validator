<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Observer;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Byte8\VatValidator\Api\VatValidatorInterface;
use Byte8\VatValidator\Model\Config;
use Byte8\VatValidator\Model\GroupResolver;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Address;
use Psr\Log\LoggerInterface;

class ValidateQuoteAddress implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly VatValidatorInterface $validator,
        private readonly GroupResolver $groupResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled() || !$this->config->validateOnCheckout()) {
            return;
        }

        /** @var Address|null $address */
        $address = $observer->getEvent()->getData('quote_address');
        if (!$address instanceof Address) {
            return;
        }

        if ($address->getAddressType() !== Address::ADDRESS_TYPE_BILLING) {
            return;
        }

        $countryId = (string) $address->getCountryId();
        $vatNumber = (string) $address->getVatId();
        if ($countryId === '' || $vatNumber === '') {
            return;
        }

        try {
            $result = $this->validator->validate($countryId, $vatNumber);
        } catch (\Throwable $e) {
            $this->logger->error('VAT validation failed for quote address: ' . $e->getMessage());
            return;
        }

        $this->logger->info(sprintf(
            'Quote address VAT validation: %s%s → %s (%s)',
            $result->getCountryCode(),
            $result->getVatNumber(),
            $result->getStatus(),
            $result->getSource()
        ));

        $address->setData('vat_is_valid', $result->getStatus() === ValidationResultInterface::STATUS_VALID);
        $address->setData('vat_request_id', $result->getRequestIdentifier());
        $address->setData('vat_request_success', $result->getStatus() !== ValidationResultInterface::STATUS_UNAVAILABLE);

        $quote = $address->getQuote();
        if ($quote === null) {
            return;
        }

        $groupId = $this->groupResolver->resolve($result, (int) $quote->getStoreId());
        if ($groupId === null) {
            return;
        }

        if ((int) $quote->getCustomerGroupId() !== $groupId) {
            $quote->setCustomerGroupId($groupId);
            $quote->collectTotals();
        }
    }
}
