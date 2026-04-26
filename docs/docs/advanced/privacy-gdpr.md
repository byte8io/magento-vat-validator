---
sidebar_position: 4
title: Privacy & GDPR
description: What gets sent to third parties, the lawful basis, and suggested privacy-policy copy.
---

# Privacy & GDPR

This module processes and stores personal / business data — read this
before installing on a production store.

## What we send to third parties

| Upstream | Data sent | Where it goes |
|---|---|---|
| EU VIES | Buyer's country code + VAT number; your country + VAT (if configured) | EC `ec.europa.eu` |
| UK HMRC | Buyer's VAT number; your VAT number (if configured) | HMRC `api.service.hmrc.gov.uk` |
| Swiss UID-Register | Buyer's UID (CHE prefix + 9 digits) | BFS `uid-wse.admin.ch` |

Both VIES and HMRC may return the buyer's company **name** and registered
**address**. We persist these in `byte8_vat_validator_log` only when
"Persist Validations to DB" is enabled.

## Lawful basis

- **Sending VAT numbers to upstreams:** Art. 6(1)(c) GDPR — *legal
  obligation*. EU and UK VAT law requires merchants to verify
  cross-border B2B VAT numbers before applying zero-rated /
  reverse-charge treatment.
- **Storing the validation log:** Art. 6(1)(c) GDPR — *legal obligation*.
  German `§147 AO` mandates 10-year retention of records supporting tax
  treatment; equivalent obligations exist in most EU member states.

You **do not need consent** to use this module for B2B validation. You
**do** need to disclose the processing in your privacy policy.

## Suggested privacy-policy copy

> We verify your VAT identification number against the European
> Commission's VIES database (and, for UK numbers, against HMRC; for
> Swiss UID, against the federal UID-Register) when you register or place
> an order, in order to apply the correct VAT treatment. The validation
> result, including any company name and address returned by VIES /
> HMRC / UID-Register, is retained for [10] years in line with our
> statutory tax-record obligations.

## Data subject rights

The validation log is keyed on `customer_id` with `ON DELETE SET NULL` —
deleting a customer detaches their log entries but preserves the
historical record for audit. If a data subject requests erasure under
GDPR Art. 17, weigh that against your Art. 17(3)(b) exemption for
"compliance with a legal obligation". In most cases the tax-record
obligation prevails — document this in your erasure-request response
process.

## What we do NOT do

- We do not phone home. The module makes no calls to byte8.io or any
  Byte8 endpoint
- We do not log to any third-party telemetry / APM service
- We do not transmit the buyer's name, email, or address to VIES /
  HMRC / UID-Register — only the country code + VAT number
