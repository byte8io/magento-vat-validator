---
sidebar_position: 2
title: Hyvä companion module
description: Live VAT validation indicator for Hyvä-themed registration forms.
---

# Hyvä companion module

`byte8/module-vat-validator-hyva` adds a live, debounced indicator under
the VAT input on Hyvä registration and address forms. Calls the standard
[REST endpoint](/docs/advanced/rest-api).

## Install

```bash
composer require byte8/module-vat-validator-hyva
bin/magento module:enable Byte8_VatValidatorHyva
bin/magento setup:upgrade
bin/magento cache:flush
```

## Where the indicator appears

| Page | Field watched |
|---|---|
| `customer/account/create` | `taxvat` |
| `customer/address/form` | `vat_id` + `country_id` |

Hyvä Checkout (Magewire-based) is not yet wired — open an issue if you
need it. (The Luma core module ships a Knockout button on Luma
checkout — see [Luma storefront widgets](/docs/frontend/luma).)

## Behaviour

- 600 ms debounce — won't spam the REST endpoint while the user types
- Recognises 2-letter prefixes (`DE…`, `GB…`) and the Swiss `CHE…` prefix
- Falls back to the country dropdown when no prefix is typed
- Hides itself silently on `unavailable` outcomes — never shows a
  misleading red badge when VIES is just slow

## Customising

Override `Byte8_VatValidatorHyva::vat-indicator.phtml` in your theme.
The component is a single self-contained Alpine `x-data` block —
drop in your own copy / icons / Tailwind classes.

## No extra JS dependencies

Alpine.js + Tailwind only — both already ship with Hyvä. Total
JavaScript added to the page: ~1 KB inline.
