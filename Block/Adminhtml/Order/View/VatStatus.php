<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Block\Adminhtml\Order\View;

use Byte8\VatValidator\Api\Data\ValidationLogInterface;
use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Byte8\VatValidator\Api\ValidationLogRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;

class VatStatus extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly ValidationLogRepositoryInterface $logRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        $order = $this->registry->registry('current_order') ?? $this->registry->registry('sales_order');

        return $order instanceof OrderInterface ? $order : null;
    }

    public function getBillingAddress(): ?OrderAddressInterface
    {
        $order = $this->getOrder();

        return $order !== null ? $order->getBillingAddress() : null;
    }

    public function hasVatNumber(): bool
    {
        $address = $this->getBillingAddress();

        return $address !== null && $address->getVatId() !== null && $address->getVatId() !== '';
    }

    /**
     * @return array{label:string,severity:string} severity is one of success|error|warning|info
     */
    public function getStatusBadge(): array
    {
        $address = $this->getBillingAddress();
        $isValid = $address?->getVatIsValid();

        if ($isValid === null || $isValid === '') {
            return ['label' => (string) __('Not verified'), 'severity' => 'info'];
        }

        return ((int) $isValid) === 1
            ? ['label' => (string) __('Valid'), 'severity' => 'success']
            : ['label' => (string) __('Invalid'), 'severity' => 'error'];
    }

    public function getLatestLogEntry(): ?ValidationLogInterface
    {
        $address = $this->getBillingAddress();
        if ($address === null) {
            return null;
        }

        $countryCode = $this->normaliseCountry((string) $address->getCountryId());
        $vatNumber = $this->normaliseVat($countryCode, (string) $address->getVatId());
        if ($countryCode === '' || $vatNumber === '') {
            return null;
        }

        $sort = $this->sortOrderBuilder
            ->setField(ValidationLogInterface::REQUESTED_AT)
            ->setDirection('DESC')
            ->create();

        $criteria = $this->searchCriteriaBuilder
            ->addFilter(ValidationLogInterface::COUNTRY_CODE, $countryCode)
            ->addFilter(ValidationLogInterface::VAT_NUMBER, $vatNumber)
            ->addSortOrder($sort)
            ->setPageSize(1)
            ->create();

        $items = $this->logRepository->getList($criteria)->getItems();

        return $items ? array_values($items)[0] : null;
    }

    public function isLogStatusValid(ValidationLogInterface $log): bool
    {
        return $log->getStatus() === ValidationResultInterface::STATUS_VALID;
    }

    public function getSourceLabel(string $source): string
    {
        return match ($source) {
            ValidationResultInterface::SOURCE_VIES => 'VIES',
            ValidationResultInterface::SOURCE_HMRC => 'HMRC',
            ValidationResultInterface::SOURCE_UID_CHE => 'UID-Register (CH)',
            default => ucfirst($source),
        };
    }

    private function normaliseCountry(string $countryCode): string
    {
        $code = strtoupper(trim($countryCode));

        return $code === 'GR' ? 'EL' : $code;
    }

    private function normaliseVat(string $country, string $raw): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if ($country !== '' && str_starts_with($clean, $country)) {
            $clean = substr($clean, strlen($country));
        }

        return $clean;
    }
}
