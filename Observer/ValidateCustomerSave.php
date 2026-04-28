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
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Validates the customer-level taxvat attribute (set during registration when
 * no address is provided). Complements ValidateCustomerAddress, which handles
 * VATs entered on a customer address.
 *
 * The two observers cooperate via ValidationCache (per-request memo) so a
 * registration carrying both customer.taxvat and a default-billing address
 * with the same number only hits the upstream once.
 */
class ValidateCustomerSave implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly VatValidatorInterface $validator,
        private readonly GroupResolver $groupResolver,
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

        /** @var Customer|null $customer */
        $customer = $observer->getEvent()->getData('customer');
        if (!$customer instanceof Customer) {
            return;
        }

        $taxvat = trim((string) $customer->getTaxvat());
        if ($taxvat === '') {
            return;
        }

        if (!$this->taxvatChanged($customer, $taxvat)) {
            return;
        }

        [$country, $number] = $this->splitTaxvat($taxvat);

        try {
            $result = $this->validator->validate($country, $number);
        } catch (\Throwable $e) {
            $this->logger->error('VAT validation failed during customer save: ' . $e->getMessage());

            return;
        }

        $this->logger->info(sprintf(
            'Customer taxvat validation: %s%s → %s (%s)',
            $result->getCountryCode(),
            $result->getVatNumber(),
            $result->getStatus(),
            $result->getSource()
        ));

        $this->surfaceCustomerNotice($result, $taxvat);

        $groupId = $this->groupResolver->resolve($result, $customer->getStoreId() ? (int) $customer->getStoreId() : null);
        if ($groupId === null || $result->getStatus() === ValidationResultInterface::STATUS_UNAVAILABLE) {
            return;
        }

        if ((int) $customer->getGroupId() === $groupId) {
            return;
        }

        // We are inside `customer_save_before`, so simply mutating the
        // in-memory customer is enough — the outer save (whose transaction
        // wraps us) persists the change atomically. Calling save() / saveAttribute()
        // from here would commit a nested transaction and break the outer
        // CustomerRepository::TransactionWrapper.
        $customer->setGroupId($groupId);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitTaxvat(string $taxvat): array
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $taxvat) ?? '');

        if (strlen($clean) >= 2 && ctype_alpha(substr($clean, 0, 2))) {
            return [substr($clean, 0, 2), substr($clean, 2)];
        }

        // No country prefix on the input — fall back to the merchant's own
        // country (configured under General → Requester Country). For domestic
        // B2B that's the right default; for cross-border buyers it's better to
        // collect VAT on the address (which carries country_id natively).
        $fallback = $this->config->getRequesterCountry() ?? '';

        return [$fallback, $clean];
    }

    private function taxvatChanged(Customer $customer, string $current): bool
    {
        if ($customer->isObjectNew()) {
            return true;
        }

        $original = (string) $customer->getOrigData('taxvat');

        return $original !== $current;
    }

    private function surfaceCustomerNotice(ValidationResultInterface $result, string $rawVat): void
    {
        try {
            if ($this->appState->getAreaCode() !== Area::AREA_FRONTEND) {
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
                    'The VAT validation service is temporarily unavailable, so we could not verify VAT number %1 right now. We will retry automatically — your account is still active.',
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
