<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model\Queue;

use Byte8\VatValidator\Api\Data\RevalidationRequestInterface;
use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Byte8\VatValidator\Api\ValidationLogRepositoryInterface;
use Byte8\VatValidator\Api\VatValidatorInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Background handler for byte8.vat.revalidate.
 *
 * Lives off the request hot path: the checkout observer publishes here
 * whenever the cached validation result is missing or older than
 * cache_ttl_hours. This keeps the audit log fresh without making the
 * customer wait on VIES/HMRC/UID-CHE.
 */
class RevalidationConsumer
{
    private const FRESHNESS_WINDOW_SECONDS = 60;

    public function __construct(
        private readonly VatValidatorInterface $validator,
        private readonly ValidationLogRepositoryInterface $logRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(RevalidationRequestInterface $request): void
    {
        $country = $request->getCountryCode();
        $number = $request->getVatNumber();

        if ($country === '' || $number === '') {
            $this->logger->warning('byte8.vat.revalidate received an empty payload — dropping.');

            return;
        }

        // Belt-and-braces against duplicate messages slipping past the
        // publisher dedupe (e.g. across multiple consumer workers, or after
        // a cache flush). If a fresh definitive result already exists,
        // skip the upstream call entirely — the queued-but-now-stale work
        // would otherwise burn HMRC quota for no new information.
        $existing = $this->logRepository->getLatestFresh($country, $number, self::FRESHNESS_WINDOW_SECONDS);
        if ($existing !== null
            && in_array($existing->getStatus(), [
                ValidationResultInterface::STATUS_VALID,
                ValidationResultInterface::STATUS_INVALID,
            ], true)) {
            $this->logger->info(sprintf(
                'Async revalidation skipped for %s%s — fresh log row already exists (%s).',
                $country,
                $number,
                $existing->getStatus()
            ));

            return;
        }

        try {
            $result = $this->validator->validate($country, $number);
            $this->logger->info(sprintf(
                'Async revalidation: %s%s → %s (%s)',
                $result->getCountryCode(),
                $result->getVatNumber(),
                $result->getStatus(),
                $result->getSource()
            ));

            // Background context enrichment: PersistValidationLog runs from
            // inside validate() above, but the consumer has no HTTP session,
            // so customer_id / customer_email come back NULL. Resolve them
            // from the queue payload (quote_id / customer_id) and backfill
            // the just-written audit row.
            $this->enrichLogContext($result->getCountryCode(), $result->getVatNumber(), $request);
        } catch (\Throwable $e) {
            // Swallow so the consumer keeps running; failed jobs are logged for ops review.
            $this->logger->error(sprintf(
                'Async revalidation failed for %s%s: %s',
                $country,
                $number,
                $e->getMessage()
            ));
        }
    }

    private function enrichLogContext(string $country, string $vat, RevalidationRequestInterface $request): void
    {
        $customerId = $request->getCustomerId();
        $email = null;

        if ($request->getQuoteId() !== null) {
            try {
                $quote = $this->cartRepository->get($request->getQuoteId());
                $email = $quote->getCustomerEmail()
                    ?: ($quote->getBillingAddress() ? $quote->getBillingAddress()->getEmail() : null)
                    ?: ($quote->getShippingAddress() ? $quote->getShippingAddress()->getEmail() : null);
                if ($customerId === null && $quote->getCustomerId()) {
                    $customerId = (int) $quote->getCustomerId();
                }
            } catch (\Throwable $e) {
                $this->logger->info(sprintf(
                    'Async revalidation: could not load quote %d for context enrichment: %s',
                    $request->getQuoteId(),
                    $e->getMessage()
                ));
            }
        }

        if ($email === null && $customerId !== null) {
            try {
                $email = $this->customerRepository->getById($customerId)->getEmail();
            } catch (\Throwable) {
                // Customer may have been deleted since the queue message was published.
            }
        }

        if ($customerId === null && ($email === null || $email === '')) {
            return;
        }

        try {
            $this->logRepository->enrichLatest($country, $vat, $customerId, $email);
        } catch (\Throwable $e) {
            $this->logger->info('Async revalidation: enrichLatest failed: ' . $e->getMessage());
        }
    }
}
