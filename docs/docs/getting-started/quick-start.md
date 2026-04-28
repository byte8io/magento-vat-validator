---
sidebar_position: 1
title: Quick start
description: Install and configure Byte8 VAT Validator in five minutes.
---

# Quick start

Six steps. Five minutes (plus an HMRC Developer Hub registration if
you validate GB VAT numbers).

## 1. Install via Composer

```bash
composer require byte8/module-vat-validator
bin/magento module:enable Byte8_VatValidator
bin/magento setup:upgrade
bin/magento cache:flush
```

## 2. Start the queue consumer

The checkout path is non-blocking — it queues async revalidation jobs
to `byte8.vat.revalidate`. Without a running consumer those jobs sit
in the DB and the audit log never refreshes:

```bash
bin/magento queue:consumers:start byte8.vat.revalidate
```

In production, supervise this under your existing consumer manager.
See [Async queue](/docs/advanced/async-queue).

## 3. Enable it

**Stores → Configuration → Byte8 → VAT Number Validator → General**

- Set **Enable VAT Validator** → **Yes**
- Fill in **Requester Country Code** (e.g. `GB`, `DE`) and **Requester
  VAT Number** with your own — VIES uses this to issue you a legally-valid
  consultation reference, and HMRC uses it for `consultationNumber`.
  Digits only for the VAT number.
- Defaults for **Request Timeout** (2 s), **Connect Timeout** (1 s),
  and **Result Cache TTL** (24 h) are appropriate for most stores —
  see [General settings](/docs/configuration/general) if you need to
  change them.

Save.

## 4. Configure HMRC OAuth (skip if you don't validate GB)

The "Check a UK VAT number" v2.0 API is application-restricted. Each
merchant registers their own application:

1. Sign up at [developer.service.hmrc.gov.uk](https://developer.service.hmrc.gov.uk).
2. Create an application and subscribe it to **"Check a UK VAT
   number"** v2.0.
3. Under **Stores → Configuration → Byte8 → VAT Number Validator → UK
   HMRC**, paste **HMRC Client ID** and **HMRC Client Secret**.

Without these, GB validations return `unavailable` (non-fatal —
checkout proceeds with full VAT applied). See [HMRC](/docs/clients/hmrc)
for full details.

## 5. Smoke-test the CLI

```bash
bin/magento byte8:vat:validate GB123456789
bin/magento byte8:vat:validate DE123456789
bin/magento byte8:vat:validate CHE-123.456.789
```

Each should print `Status: valid` and exit 0. If any return
`unavailable`, the upstream is briefly down — try again in 30 seconds.

## 6. Wire up customer-group mapping

The module **assigns customer groups**, but doesn't configure tax rules.
Before turning on auto-assignment, set up the chain:

1. **Stores → Customer Groups** — create "B2B EU Valid" with a tax class
2. **Stores → Tax Zones and Rates** — create / confirm a 0% rate
3. **Stores → Tax Rules** — rule mapping that customer tax class +
   product tax class to 0%

Then back in the module config under **Customer Group Mapping**:

- **Auto-Assign Customer Group** → **Yes**
- **Group for Intra-EU / UK Valid VAT (Zero Tax)** → "B2B EU Valid"

The full worked example, including all three groups (domestic / intra-EU
valid / invalid), is on the [Customer Groups](/docs/configuration/customer-groups)
page.

## 7. Place a test order

Log in as a B2B customer with a valid intra-EU VAT, change the billing
country at checkout, and verify the tax line drops to 0 before the order
is placed. Then check **Stores → Byte8 → VAT Validation Log** — the call
should show up immediately, with `requestIdentifier` populated.

You're done. The module fires on every customer-address save (sync) and
every quote-address save (async / cache-backed) from now on. The Luma
storefront also renders a live "Verify VAT" button on registration,
account-edit, and checkout — see
[Luma storefront widgets](/docs/frontend/luma).
