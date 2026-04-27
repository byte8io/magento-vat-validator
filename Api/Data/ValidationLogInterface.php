<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Api\Data;

interface ValidationLogInterface
{
    public const ENTITY_ID = 'entity_id';
    public const CUSTOMER_ID = 'customer_id';
    public const CUSTOMER_EMAIL = 'customer_email';
    public const STORE_ID = 'store_id';
    public const COUNTRY_CODE = 'country_code';
    public const VAT_NUMBER = 'vat_number';
    public const STATUS = 'status';
    public const SOURCE = 'source';
    public const REQUEST_IDENTIFIER = 'request_identifier';
    public const COMPANY_NAME = 'company_name';
    public const COMPANY_ADDRESS = 'company_address';
    public const REQUEST_PAYLOAD = 'request_payload';
    public const RESPONSE_PAYLOAD = 'response_payload';
    public const REQUESTED_AT = 'requested_at';

    public function getEntityId(): ?int;

    /**
     * @param int|null $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    public function getCustomerId(): ?int;

    public function setCustomerId(?int $customerId): self;

    public function getCustomerEmail(): ?string;

    public function setCustomerEmail(?string $email): self;

    public function getStoreId(): int;

    public function setStoreId(int $storeId): self;

    public function getCountryCode(): string;

    public function setCountryCode(string $countryCode): self;

    public function getVatNumber(): string;

    public function setVatNumber(string $vatNumber): self;

    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function getSource(): string;

    public function setSource(string $source): self;

    public function getRequestIdentifier(): ?string;

    public function setRequestIdentifier(?string $identifier): self;

    public function getCompanyName(): ?string;

    public function setCompanyName(?string $name): self;

    public function getCompanyAddress(): ?string;

    public function setCompanyAddress(?string $address): self;

    public function getRequestPayload(): ?string;

    public function setRequestPayload(?string $payload): self;

    public function getResponsePayload(): ?string;

    public function setResponsePayload(?string $payload): self;

    public function getRequestedAt(): ?string;

    public function setRequestedAt(?string $requestedAt): self;
}
