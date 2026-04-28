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
use Byte8\VatValidator\Model\ValidationLogFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Persists every (valid|invalid) validation result to the audit log table.
 *
 * Dedupe note: a single Magento checkout step may save the quote address
 * multiple times, and our format pre-check fires once per save. Without a
 * guard we'd write 4-6 identical rows per step. We skip a save when an
 * identical (country, vat, status, source) row was written in the last 60s
 * — the audit story still works (one row per buyer attempt), and the §147
 * AO retention obligation only requires that *a* validation be retained,
 * not every duplicate.
 */
class PersistValidationLog implements ObserverInterface
{
    private const DEDUPE_WINDOW_SECONDS = 60;

    public function __construct(
        private readonly Config $config,
        private readonly ValidationLogFactory $logFactory,
        private readonly ValidationLogRepositoryInterface $logRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isLogEnabled()) {
            return;
        }

        $result = $observer->getEvent()->getData('result');
        if (!$result instanceof ValidationResultInterface) {
            return;
        }

        $status = $result->getStatus();
        if ($status !== ValidationResultInterface::STATUS_VALID
            && $status !== ValidationResultInterface::STATUS_INVALID) {
            return;
        }

        $existing = $this->logRepository->getLatestFresh(
            $result->getCountryCode(),
            $result->getVatNumber(),
            self::DEDUPE_WINDOW_SECONDS
        );
        if ($existing !== null
            && $existing->getStatus() === $status
            && $existing->getSource() === $result->getSource()) {
            return;
        }

        try {
            $entry = $this->logFactory->create();
            $entry
                ->setCustomerId($this->resolveCustomerId())
                ->setCustomerEmail($this->resolveCustomerEmail())
                ->setStoreId((int) $this->storeManager->getStore()->getId())
                ->setCountryCode($result->getCountryCode())
                ->setVatNumber($result->getVatNumber())
                ->setStatus($status)
                ->setSource($result->getSource())
                ->setRequestIdentifier($result->getRequestIdentifier())
                ->setCompanyName($result->getName())
                ->setCompanyAddress($result->getAddress())
                ->setRequestPayload((string) $result->getDataByKey('request_payload') ?: null)
                ->setResponsePayload((string) $result->getDataByKey('response_payload') ?: null);

            $this->logRepository->save($entry);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to persist VAT validation log entry: ' . $e->getMessage());
        }
    }

    private function resolveCustomerId(): ?int
    {
        try {
            $id = (int) $this->customerSession->getCustomerId();
            if ($id > 0) {
                return $id;
            }
        } catch (\Throwable) {
        }

        // Guest checkout: pull from the active quote if it carries a logged-in customer.
        try {
            $quote = $this->checkoutSession->getQuote();
            $customerId = (int) $quote->getCustomerId();

            return $customerId > 0 ? $customerId : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveCustomerEmail(): ?string
    {
        try {
            $email = $this->customerSession->getCustomer()->getEmail();
            if ($email) {
                return (string) $email;
            }
        } catch (\Throwable) {
        }

        // Guest checkout: read from the quote billing address (filled in
        // before the order is placed) or the quote itself.
        try {
            $quote = $this->checkoutSession->getQuote();
            $email = $quote->getCustomerEmail()
                ?: $quote->getBillingAddress()->getEmail()
                ?: $quote->getShippingAddress()->getEmail();

            return $email ? (string) $email : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
