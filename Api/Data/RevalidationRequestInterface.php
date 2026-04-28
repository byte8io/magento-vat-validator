<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Api\Data;

interface RevalidationRequestInterface
{
    /**
     * @return string
     */
    public function getCountryCode(): string;

    /**
     * @param string $countryCode
     * @return $this
     */
    public function setCountryCode(string $countryCode): self;

    /**
     * @return string
     */
    public function getVatNumber(): string;

    /**
     * @param string $vatNumber
     * @return $this
     */
    public function setVatNumber(string $vatNumber): self;

    /**
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * @param int|null $storeId
     * @return $this
     */
    public function setStoreId(?int $storeId): self;

    /**
     * @return int|null
     */
    public function getCustomerId(): ?int;

    /**
     * @param int|null $customerId
     * @return $this
     */
    public function setCustomerId(?int $customerId): self;

    /**
     * @return int|null
     */
    public function getQuoteId(): ?int;

    /**
     * @param int|null $quoteId
     * @return $this
     */
    public function setQuoteId(?int $quoteId): self;
}
