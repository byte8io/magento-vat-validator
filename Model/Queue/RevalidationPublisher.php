<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model\Queue;

use Byte8\VatValidator\Api\Data\RevalidationRequestInterface;
use Byte8\VatValidator\Api\Data\RevalidationRequestInterfaceFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Psr\Log\LoggerInterface;

class RevalidationPublisher
{
    public const TOPIC = 'byte8.vat.revalidate';

    private const DEDUPE_CACHE_PREFIX = 'byte8_vat_validator_publish_';
    private const DEDUPE_CACHE_TAG = 'BYTE8_VAT_VALIDATOR_PUBLISH';
    private const DEDUPE_WINDOW_SECONDS = 30;

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly RevalidationRequestInterfaceFactory $requestFactory,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    public function publish(
        string $countryCode,
        string $vatNumber,
        ?int $storeId = null,
        ?int $customerId = null,
        ?int $quoteId = null
    ): void {
        // Dedupe: a single shipping-step submit fires sales_quote_address_save_before
        // 3-5 times (estimate-shipping-methods → assign-address → collect-totals).
        // Without a marker we'd publish a queue message per save, then the
        // consumer would burn 3-5 HMRC calls for what the user did once.
        $cacheKey = self::DEDUPE_CACHE_PREFIX . sha1(strtoupper($countryCode . ':' . $vatNumber));
        if ($this->cache->load($cacheKey)) {
            return;
        }

        $request = $this->requestFactory->create();
        $request
            ->setCountryCode($countryCode)
            ->setVatNumber($vatNumber)
            ->setStoreId($storeId)
            ->setCustomerId($customerId)
            ->setQuoteId($quoteId);

        try {
            $this->publisher->publish(self::TOPIC, $request);
            $this->cache->save('1', $cacheKey, [self::DEDUPE_CACHE_TAG], self::DEDUPE_WINDOW_SECONDS);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Failed to publish %s for %s%s: %s',
                self::TOPIC,
                $countryCode,
                $vatNumber,
                $e->getMessage()
            ));
        }
    }
}
