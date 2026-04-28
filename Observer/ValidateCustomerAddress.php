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
use Magento\Framework\App\State as AppState;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Psr\Log\LoggerInterface;

class ValidateCustomerAddress implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly VatValidatorInterface $validator,
        private readonly GroupResolver $groupResolver,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly MessageManagerInterface $messageManager,
        private readonly AppState $appState,
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

        $this->surfaceCustomerNotice($result, $vatNumber);

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

    /**
     * Post a session message so the buyer sees the validation outcome on the
     * next page. Storefront-only — admin-area address saves should not produce
     * customer-facing notices. We deliberately do NOT throw / block the save:
     * an invalid number still persists, but the buyer is informed.
     */
    private function surfaceCustomerNotice(ValidationResultInterface $result, string $rawVat): void
    {
        try {
            if ($this->appState->getAreaCode() !== \Magento\Framework\App\Area::AREA_FRONTEND) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $label = trim($result->getCountryCode() . $result->getVatNumber());
        if ($label === '') {
            $label = $rawVat;
        }

        switch ($result->getStatus()) {
            case ValidationResultInterface::STATUS_INVALID:
                $this->messageManager->addErrorMessage(__(
                    'We could not verify VAT number %1. Please double-check it. Until it can be verified, reverse-charge / zero-rated VAT will not be applied to your orders.',
                    $label
                ));

                return;

            case ValidationResultInterface::STATUS_UNAVAILABLE:
                $this->messageManager->addWarningMessage(__(
                    'The VAT validation service is temporarily unavailable, so we could not verify VAT number %1 right now. We will retry automatically — your order can still proceed.',
                    $label
                ));

                return;

            case ValidationResultInterface::STATUS_VALID:
                $this->messageManager->addSuccessMessage(__(
                    'VAT number %1 has been verified.',
                    $label
                ));
        }
    }
}
