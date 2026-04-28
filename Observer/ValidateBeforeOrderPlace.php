<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Observer;

use Byte8\VatValidator\Api\Data\ValidationLogInterface;
use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Byte8\VatValidator\Api\ValidationLogRepositoryInterface;
use Byte8\VatValidator\Model\Config;
use Byte8\VatValidator\Model\FormatValidator;
use Byte8\VatValidator\Model\GroupResolver;
use Byte8\VatValidator\Model\Queue\RevalidationPublisher;
use Byte8\VatValidator\Model\VatValidator;
use Byte8\VatValidator\Model\ValidationResultFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

/**
 * Belt-and-braces validation pass at order placement.
 *
 * `sales_quote_address_save_before` is the primary hook but Magento's
 * resource-model change detection sometimes skips the save (e.g. when
 * the customer edits the address in-place without changing fields), so
 * the observer never fires. This hook runs exactly once per submit and
 * guarantees:
 *   - the audit log has a row for the order's VAT (compliance)
 *   - the quote.customer_group_id reflects the latest validation result
 *   - vat_is_valid / vat_request_id are stamped on the billing address
 *
 * No upstream HTTP call is made when the cache is fresh (24h TTL): this
 * almost always reduces to a DB lookup.
 */
class ValidateBeforeOrderPlace implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ValidationLogRepositoryInterface $logRepository,
        private readonly ValidationResultFactory $resultFactory,
        private readonly RevalidationPublisher $publisher,
        private readonly GroupResolver $groupResolver,
        private readonly FormatValidator $formatValidator,
        private readonly EventManagerInterface $eventManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled() || !$this->config->validateOnCheckout()) {
            return;
        }

        /** @var Quote|null $quote */
        $quote = $observer->getEvent()->getData('quote');
        if (!$quote instanceof Quote) {
            return;
        }

        $billing = $quote->getBillingAddress();
        if ($billing === null) {
            return;
        }

        $countryId = (string) $billing->getCountryId();
        $vatNumber = (string) $billing->getVatId();
        if ($countryId === '' || $vatNumber === '') {
            return;
        }

        $storeId = (int) $quote->getStoreId();
        $normalizedCountry = strtoupper($countryId);
        $normalizedNumber = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($vatNumber)) ?? '');
        if (str_starts_with($normalizedNumber, $normalizedCountry)) {
            $normalizedNumber = substr($normalizedNumber, strlen($normalizedCountry));
        }

        $formatResult = $this->formatValidator->check($normalizedCountry, $normalizedNumber);
        if ($formatResult !== null) {
            $billing->setData('vat_is_valid', false);
            $billing->setData('vat_request_success', true);
            $this->eventManager->dispatch(VatValidator::EVENT_VALIDATED, ['result' => $formatResult]);

            return;
        }

        $cached = $this->logRepository->getLatestFresh(
            $normalizedCountry,
            $normalizedNumber,
            $this->config->getCacheTtlSeconds($storeId)
        );

        if ($cached === null) {
            // Fresh enough log row missing — kick off async revalidation but
            // do not block the order. Order placement is never gated on the
            // upstream's responsiveness, by design.
            $this->publisher->publish(
                $normalizedCountry,
                $normalizedNumber,
                $storeId,
                $quote->getCustomerId() ? (int) $quote->getCustomerId() : null,
                $quote->getId() ? (int) $quote->getId() : null
            );
            $this->logger->info(sprintf(
                'Order placement: VAT cache miss for %s%s — enqueued async revalidation; order proceeds.',
                $normalizedCountry,
                $normalizedNumber
            ));

            return;
        }

        $result = $this->resultFactory->create(['data' => [
            'country_code' => $cached->getCountryCode(),
            'vat_number' => $cached->getVatNumber(),
            'status' => $cached->getStatus(),
            'source' => $cached->getSource(),
            'name' => $cached->getCompanyName(),
            'address' => $cached->getCompanyAddress(),
            'request_identifier' => $cached->getRequestIdentifier(),
        ]]);

        $billing->setData('vat_is_valid', $result->getStatus() === ValidationResultInterface::STATUS_VALID);
        $billing->setData('vat_request_id', $result->getRequestIdentifier());
        $billing->setData('vat_request_success', true);

        $groupId = $this->groupResolver->resolve($result, $storeId);
        if ($groupId !== null && (int) $quote->getCustomerGroupId() !== $groupId) {
            $quote->setCustomerGroupId($groupId);
            $quote->collectTotals();
        }
    }
}
