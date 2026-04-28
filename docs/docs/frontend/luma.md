---
sidebar_position: 1
title: Luma storefront widgets
description: Live "Verify VAT" buttons on Luma registration, account-edit, and checkout — shipped with the core module.
---

# Luma storefront widgets

The core module ships live "Verify VAT" buttons for the standard Luma
storefront — no companion module required. Two surfaces are wired out
of the box:

| Surface | Where | How |
|---|---|---|
| Registration & account edit | `customer/account/create`, `customer/account/edit` | Standalone vanilla RequireJS module |
| Checkout | `checkout/index/index`, under the shipping address form | Knockout `uiComponent` |

Both call the [REST API](/docs/advanced/rest-api) — registration uses
`/validate` (synchronous, fresh), checkout uses `/lookup` (cache-aware,
queue-backed).

## Why a button instead of debounce-on-blur

A blur-triggered validator burns HMRC's per-application rate limit
every time the buyer tabs in and out of the field while filling the
form. The explicit button avoids that and gives the buyer agency. The
trade-off — one extra click — is acceptable for a B2B buyer entering
their company VAT number once.

## Registration & account-edit

Vanilla RequireJS module
(`view/frontend/web/js/registration-vat-validate.js`) bootstrapped via
`text/x-magento-init` from `Block\Form\RegisterVatInit` and
`templates/form/register-vat-init.phtml`. Layout XML wires it into
`customer_account_create` and `customer_account_edit`.

How the country is resolved:

1. Leading 2-letter prefix on the VAT input (`DE…`, `GB…`).
2. Leading 3-letter `CHE` prefix for Swiss UIDs.
3. Falls back to the merchant's configured **Requester Country Code**
   if no prefix is present.

That priority order means a buyer who types `GB123456789` is routed to
HMRC even if the country dropdown is showing `DE`.

The result panel is colour-coded — green (valid) / red (invalid) /
amber (unavailable) — and shows the upstream-returned company name
plus the qualified-confirmation reference (VIES `requestIdentifier` or
HMRC `consultationNumber`).

## Checkout

Knockout `uiComponent` (`view/frontend/web/js/view/vat-validate-button.js`)
registered in `view/frontend/layout/checkout_index_index.xml` under
`shippingAddress.before-shipping-method-form`. The KO template lives at
`view/frontend/web/template/vat-validate-button.html`.

A few quirks worth knowing:

- **Re-anchors itself via DOM relocation.** The button moves itself
  directly under `input[name="vat_id"]` on init and again whenever
  `quote.shippingAddress` changes. This handles the back-from-payment
  path where the shipping form is re-rendered.
- **Reads live form values first.** It uses whatever the buyer has
  typed *right now*, falling back to the persisted quote address only
  when the form is empty. So a buyer can paste a new VAT, click
  Verify, and see the result without first triggering a quote save.
- **AbortController-cancelled in-flight.** A rapid double-click won't
  produce a race — the second click cancels the first.

## Validate-on-checkout vs the click

These two paths exist side by side:

1. **Server-side `Observer\ValidateQuoteAddress`** runs on every quote
   address save. It reads the persisted result from the cache and
   queues an async revalidation if stale — non-blocking. See
   [Async queue](/docs/advanced/async-queue).
2. **Client-side "Verify VAT" button** is the buyer's *interactive*
   touchpoint. They click it, they see the answer immediately, with
   the upstream-returned company details for confidence.

The button is the only reliable in-flight UX surface on Luma checkout
because Luma doesn't render `messageManager` flash messages between
AJAX checkout steps — the server-side notices the observer posts
*do* surface, but only on the order-success page after the full
re-render. If you need mid-checkout feedback, the button is it.

## Shared CSS

`view/frontend/web/css/vat-validator.css` is loaded via
`default_head_blocks.xml` and used by both the registration widget and
the checkout button. The same `byte8-vat-pill` style language is
re-used on the [admin grid](/docs/validation-log/admin-grid)'s status
column — keep both consistent if you re-skin.

## Customising

The widgets are designed to be overridden in your theme:

- **Registration template** — copy
  `view/frontend/templates/form/register-vat-init.phtml` into your
  theme and adjust the markup the JS attaches to.
- **Checkout KO template** — override
  `Byte8_VatValidator/template/vat-validate-button.html` via standard
  Magento template fallback.
- **Layout containers** — if your theme has moved the VAT input out of
  `shippingAddress.before-shipping-method-form`, override
  `checkout_index_index.xml` in your theme module to point at the
  right container.

If you've replaced Luma with Hyvä or a headless storefront, see the
[Hyvä](/docs/frontend/hyva) or [VelaFront](/docs/frontend/velafront)
pages for the equivalents.
