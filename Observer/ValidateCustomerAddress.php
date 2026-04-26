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
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class ValidateCustomerAddress implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly VatValidatorInterface $validator,
        private readonly GroupResolver $groupResolver,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled() || !$this->config->validateOnCustomerSave()) {
            return;
        }

        /** @var \Magento\Customer\Model\Address|null $address */
        $address = $observer->getEvent()->getData('customer_address');
        if ($address === null) {
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
            $this->logger->error('VAT validation failed during customer address save: ' . $e->getMessage());
            return;
        }

        $isBilling = (bool) $address->getIsDefaultBilling() || (bool) $address->getIsPrimaryBilling();

        $this->logger->info(sprintf(
            'Customer address VAT validation: %s%s → %s (%s)',
            $result->getCountryCode(),
            $result->getVatNumber(),
            $result->getStatus(),
            $result->getSource()
        ));

        if (!$isBilling) {
            return;
        }

        $customerId = (int) $address->getParentId();
        if ($customerId <= 0) {
            return;
        }

        $groupId = $this->groupResolver->resolve($result);
        if ($groupId === null || $result->getStatus() === ValidationResultInterface::STATUS_UNAVAILABLE) {
            return;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            if ((int) $customer->getGroupId() === $groupId) {
                return;
            }

            $customer->setGroupId($groupId);
            $this->customerRepository->save($customer);
        } catch (\Throwable $e) {
            $this->logger->error('VAT validator: failed to update customer group: ' . $e->getMessage());
        }
    }
}
