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
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class PersistValidationLog implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ValidationLogFactory $logFactory,
        private readonly ValidationLogRepositoryInterface $logRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
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

            return $id > 0 ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveCustomerEmail(): ?string
    {
        try {
            $email = $this->customerSession->getCustomer()->getEmail();

            return $email ? (string) $email : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
