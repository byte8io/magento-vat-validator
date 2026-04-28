---
sidebar_position: 1
slug: /
title: Introduction
description: Byte8 VAT Validator — EU VIES + UK HMRC + Swiss UID validation for Magento 2.
---

# Byte8 VAT Validator

A free, MIT-licensed Magento 2 module that validates B2B buyers' VAT
numbers against the **EU VIES** REST API, **UK HMRC's "Check a UK VAT
number" v2.0** (OAuth 2.0), and the **Swiss UID-Register** — at customer
registration **and** at checkout — and automatically moves validated
customers into a configurable "Zero Tax" customer group so the right
tax rules fire immediately.

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
+ CH coverage, and a **non-blocking** checkout path — every quote
address save reads from a TTL-bounded result cache and queues an async
revalidation if stale. Order placement is never blocked on VIES / HMRC
/ UID-CHE responsiveness.

## What's new in v1.0

- **Async checkout queue** — `byte8.vat.revalidate` topic, DB-backed by
  default. See [Async queue](/docs/advanced/async-queue).
- **HMRC OAuth 2.0** — "Check a UK VAT number" v2.0 is
  application-restricted. Each merchant must register their own
  application. See [HMRC](/docs/clients/hmrc).
- **Live "Verify VAT" buttons** on Luma registration, account-edit, and
  checkout — shipped with the core module. See
  [Luma storefront widgets](/docs/frontend/luma).
- **Cache-aware REST `/lookup`** for headless storefronts — see
  [REST API](/docs/advanced/rest-api).
- **Customer-facing notices** on the storefront when a VAT can't be
  verified (non-blocking — order proceeds with full VAT applied).

## Where to start

If you've never installed the module, jump straight to the
[Quick start](/docs/getting-started/quick-start) — it's a 5-minute
Composer install + a couple of config flags.

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
