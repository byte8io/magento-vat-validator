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
use Byte8\VatValidator\Model\ValidationResult;
use Byte8\VatValidator\Model\ValidationResultFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Model\Quote\Address;
use Psr\Log\LoggerInterface;

/**
 * Non-blocking checkout-time VAT handling.
 *
 * The synchronous external HTTP call has been removed. We look up the most
 * recent persisted result from byte8_vat_validator_log; if it's fresh enough
 * (cache_ttl_hours), we apply group / vat_is_valid in-request. If the cache
 * is missing or stale we publish to byte8.vat.revalidate and return — order
 * placement is never blocked on VIES/HMRC/UID-CHE responsiveness.
 *
 * Format errors (e.g. a 10-digit GB VAT) are caught synchronously by
 * FormatValidator: those are unambiguous, network-free, and surfaced as a
 * messageManager notice so the customer is informed without blocking the
 * order. Defaulting to "no VAT exemption applied" is preferable to refusing
 * the order — a customer who proceeds with an invalid VAT pays full VAT,
 * which is the legally safe default.
 */
class ValidateQuoteAddress implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ValidationLogRepositoryInterface $logRepository,
        private readonly ValidationResultFactory $resultFactory,
        private readonly RevalidationPublisher $publisher,
        private readonly GroupResolver $groupResolver,
        private readonly FormatValidator $formatValidator,
        private readonly EventManagerInterface $eventManager,
        private readonly MessageManagerInterface $messageManager,
        private readonly AppState $appState,
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

        // Validate any quote address with a VAT — billing OR shipping.
        // Some checkout themes (Hyvä, custom Luma) collect VAT on the shipping
        // step; the customer would then never see a notice if we filtered to
        // billing only. The same VAT entered on both addresses dedupes via
        // the in-memory ValidationCache.
        $countryId = (string) $address->getCountryId();
        $vatNumber = (string) $address->getVatId();

        if ($countryId === '' || $vatNumber === '') {
            return;
        }

        $quote = $address->getQuote();
        $storeId = $quote !== null ? (int) $quote->getStoreId() : null;
        $normalizedNumber = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($vatNumber)) ?? '');
        $normalizedCountry = strtoupper($countryId);
        if (str_starts_with($normalizedNumber, $normalizedCountry)) {
            $normalizedNumber = substr($normalizedNumber, strlen($normalizedCountry));
        }
        $label = $normalizedCountry . $normalizedNumber;

        // 1. Synchronous format check — catches obviously malformed input
        // (wrong digit count, wrong charset). Cheap, no I/O. We dispatch
        // the standard validate event so PersistValidationLog writes the
        // audit row, surface a notice, and let checkout proceed. Customer
        // pays full VAT (legally-safe default) until the number is fixed.
        $formatResult = $this->formatValidator->check($normalizedCountry, $normalizedNumber);

        if ($formatResult !== null) {
            $address->setData('vat_is_valid', false);
            $address->setData('vat_request_success', true);
            $this->eventManager->dispatch(VatValidator::EVENT_VALIDATED, ['result' => $formatResult]);
            $this->surfaceCheckoutNotice($formatResult, $label);

            return;
        }

        $cached = $this->logRepository->getLatestFresh(
            $normalizedCountry,
            $normalizedNumber,
            $this->config->getCacheTtlSeconds($storeId)
        );

        if ($cached === null) {
            $this->publisher->publish(
                $normalizedCountry,
                $normalizedNumber,
                $storeId,
                $quote !== null && $quote->getCustomerId() ? (int) $quote->getCustomerId() : null,
                $quote !== null && $quote->getId() ? (int) $quote->getId() : null
            );
            $this->logger->info(sprintf(
                'Quote VAT cache miss for %s%s — enqueued async revalidation; checkout proceeds with previous group.',
                $normalizedCountry,
                $normalizedNumber
            ));

            return;
        }

        $result = $this->resultFromLog($cached);

        $address->setData('vat_is_valid', $result->getStatus() === ValidationResultInterface::STATUS_VALID);
        $address->setData('vat_request_id', $result->getRequestIdentifier());
        $address->setData('vat_request_success', true);

        if ($result->getStatus() === ValidationResultInterface::STATUS_INVALID) {
            $this->surfaceCheckoutNotice($result, $label);

            return;
        }

        if ($quote === null) {
            return;
        }

        $groupId = $this->groupResolver->resolve($result, $storeId);
        if ($groupId === null) {
            return;
        }

        if ((int) $quote->getCustomerGroupId() !== $groupId) {
            $quote->setCustomerGroupId($groupId);
            $quote->collectTotals();
        }
    }

    /**
     * Storefront-only error notice. Non-blocking — the order proceeds, but
     * the customer is informed that their VAT could not be verified and that
     * full VAT will be applied. Admin-area saves are silent so back-office
     * order entry is never affected.
     */
    private function surfaceCheckoutNotice(ValidationResultInterface $result, string $label): void
    {
        try {
            if ($this->appState->getAreaCode() !== Area::AREA_FRONTEND) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $reason = $result->getMessage()
            ? __('VAT number %1 could not be verified: %2 Full VAT will be applied — please correct the number to qualify for reverse-charge / zero-rated VAT.', $label, $result->getMessage())
            : __('VAT number %1 could not be verified. Full VAT will be applied — please correct the number to qualify for reverse-charge / zero-rated VAT.', $label);

        $this->messageManager->addErrorMessage($reason);
    }

    private function resultFromLog(ValidationLogInterface $log): ValidationResult
    {
        return $this->resultFactory->create(['data' => [
            'country_code' => $log->getCountryCode(),
            'vat_number' => $log->getVatNumber(),
            'status' => $log->getStatus(),
            'source' => $log->getSource(),
            'name' => $log->getCompanyName(),
            'address' => $log->getCompanyAddress(),
            'request_identifier' => $log->getRequestIdentifier(),
        ]]);
    }
}
