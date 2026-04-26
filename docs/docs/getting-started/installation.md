---
sidebar_position: 2
title: Installation
description: Install Byte8 VAT Validator via Composer or manually.
---

# Installation

## Requirements

- Magento 2.4.x
- PHP 8.1+
- No `ext-soap` required
- No external account / API key

## Composer (recommended)

```bash
composer require byte8/module-vat-validator
bin/magento module:enable Byte8_VatValidator
bin/magento setup:upgrade
bin/magento cache:flush
```

This also creates the `byte8_vat_validator_log` table for the audit log.

## Manual install

1. Copy the module files to `app/code/Byte8/VatValidator/`
2. Run:

```bash
bin/magento module:enable Byte8_VatValidator
bin/magento setup:upgrade
bin/magento cache:flush
```

## Optional companion modules

| Package | Purpose |
|---|---|
| `byte8/module-vat-validator-hyva` | Live indicator on Hyvä registration / address forms |
| `@velafront/vat-validator` | React component for VelaFront / Next.js storefronts |

## Verify the install

```bash
bin/magento module:status | grep Byte8_VatValidator
bin/magento byte8:vat:validate GB123456789
```
