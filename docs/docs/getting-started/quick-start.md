---
sidebar_position: 1
title: Quick start
description: Install and configure Byte8 VAT Validator in five minutes.
---

# Quick start

Five steps. Five minutes. No external account required.

## 1. Install via Composer

```bash
composer require byte8/module-vat-validator
bin/magento module:enable Byte8_VatValidator
bin/magento setup:upgrade
bin/magento cache:flush
```

## 2. Enable it

**Stores → Configuration → Byte8 → VAT Number Validator → General**

- Set **Enable VAT Validator** → **Yes**
- Fill in **Requester Country Code** (e.g. `GB`, `DE`) and **Requester
  VAT Number** with your own — VIES uses this to issue you a legally-valid
  consultation reference

Save.

## 3. Smoke-test the CLI

```bash
bin/magento byte8:vat:validate GB123456789
bin/magento byte8:vat:validate DE123456789
bin/magento byte8:vat:validate CHE-123.456.789
```

Each should print `Status: valid` and exit 0. If any return
`unavailable`, the upstream is briefly down — try again in 30 seconds.

## 4. Wire up customer-group mapping

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

## 5. Place a test order

Log in as a B2B customer with a valid intra-EU VAT, change the billing
country at checkout, and verify the tax line drops to 0 before the order
is placed. Then check **Stores → Byte8 → VAT Validation Log** — the call
should show up immediately, with `requestIdentifier` populated.

You're done. The module fires on every customer-address save and every
quote-billing-address save from now on.
