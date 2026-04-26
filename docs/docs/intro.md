---
sidebar_position: 1
slug: /
title: Introduction
description: Byte8 VAT Validator — EU VIES + UK HMRC + Swiss UID validation for Magento 2.
---

# Byte8 VAT Validator

A free, MIT-licensed Magento 2 module that validates B2B buyers' VAT
numbers against the **EU VIES** REST API, the **UK HMRC** public lookup,
and the **Swiss UID-Register** — at customer registration **and** at
checkout — and automatically moves validated customers into a configurable
"Zero Tax" customer group so the right tax rules fire immediately.

## Why this exists

Magento's native VAT validation has three problems in 2026:

1. **It hits the deprecated VIES SOAP endpoint.** Stores without
   `ext-soap` fail silently. The EC has signalled the SOAP endpoint
   will be retired.
2. **It has no UK coverage post-Brexit.** GB VAT numbers can't be
   validated at all.
3. **It only fires at customer save**, not at checkout. A logged-in B2B
   buyer who changes country at checkout gets the wrong tax.

This module is the modern replacement: REST-only (no `ext-soap`), EU + UK
+ CH coverage, and re-validates on every billing-address save so the tax
rule applies before the customer hits "Place Order".

## Where to start

If you've never installed the module, jump straight to the
[Quick start](/docs/getting-started/quick-start) — it's a 5-minute
Composer install + one config flag.

If you're a German merchant who's reading this because you need a
queryable validation log for §147 AO retention, start at
[Validation log overview](/docs/validation-log/overview).

If you're building a headless storefront, the
[REST API page](/docs/advanced/rest-api) and the
[VelaFront / Hyvä](/docs/frontend/velafront) widgets are what you want.

## What this module is NOT

- **Not** a tax-rule manager. It assigns customer groups; you still
  configure the tax classes + tax rules in standard Magento. See
  [Customer Group Mapping](/docs/configuration/customer-groups) for the
  worked example.
- **Not** a German `Steuernummer` validator. The Steuernummer is a
  domestic Finanzamt identifier with no public lookup API — only
  syntactic validation per Bundesland is possible. Out of scope.
- **Not** a billing / invoicing module. For invoice sync to Sage / Xero,
  see [Byte8 Ledger](https://byte8.io/ledger).
