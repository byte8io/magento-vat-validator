---
sidebar_position: 1
title: General settings
description: The master switch, request timeouts, and the result cache TTL.
---

# General settings

Found at **Stores → Configuration → Byte8 → VAT Number Validator → General**.

| Field | Default | Purpose |
|---|---|---|
| **Enable VAT Validator** | No | Master switch. When off, no observers fire and no upstream calls are made. |
| **Validate on Customer Save** | Yes | Run **synchronous** validation when a customer or address is saved (the customer is waiting on the form, so a synchronous call is appropriate). |
| **Validate on Checkout** | Yes | Apply cached / async-revalidated VAT result during quote-address save. **Non-blocking** — the checkout never waits on VIES/HMRC/UID-CHE. See [Async queue](/docs/advanced/async-queue) for the full mechanism. |
| **Request Timeout (seconds)** | 2 | Hard response timeout for each upstream call. |
| **Connect Timeout (seconds)** | 1 | TCP/TLS connect timeout. Fails fast on unreachable upstreams. |
| **Result Cache TTL (hours)** | 24 | How long a stored validation outcome is considered fresh during checkout. Within this window, the persisted result is reused (no upstream call). When stale, the checkout request returns immediately and an asynchronous queue job revalidates in the background. |
| **Requester Country Code** | — | Your own ISO 2-letter code, sent to VIES so the response includes a legally-valid consultation reference. |
| **Requester VAT Number** | — | Your own VAT number, **digits only**. Sent to VIES alongside the requester country, and to HMRC as the path-segment requester VRN. |

## The two timeouts

The split between **connect** and **request** timeouts matters when an
upstream is *unreachable* (DNS failure, dropped routes, network split)
versus *slow* (handshake completes, response is taking too long).

- **Connect timeout (1 s)** — caps how long we wait to *open* the TCP
  connection. If the upstream is offline at the network layer, we fail
  inside this budget instead of burning the full request timeout.
- **Request timeout (2 s)** — caps the total round-trip including
  response body. With the new async checkout architecture the
  customer-save path is the only synchronous caller; 2 s is enough for
  a healthy VIES/HMRC and short enough that an upset upstream doesn't
  stall the form.

Together these mean a worst-case dead network adds **~1 s** to a
customer save, not 5 s.

:::tip Don't bump the request timeout reflexively
Pre-v1.0 the default was 5 s. Customer-facing observer paths were
synchronous, so a slow VIES would stall checkout. Since v1.0 the
checkout path is queue-backed (see [Async queue](/docs/advanced/async-queue)),
so longer timeouts only buy patience on the customer-save flow.
Raise to 5 s only if you observe false `unavailable` outcomes in
the audit log.
:::

## Result Cache TTL

The cache TTL controls a single question: **how stale is acceptable
when applying the right tax group at checkout?**

- VAT registration status rarely flips minute-to-minute. A merchant's
  registration is granted, valid until cancelled, and the typical
  cancellation event is months in advance.
- The default 24 h means a customer who validated yesterday is treated
  as valid today without burning a synchronous round-trip — and the
  audit log stays current via the consumer.
- Drop to 1 h if you trade with merchants whose registrations are
  high-churn (small one-person Ltds, freshly-deregistered shells).
  Raise to 168 h (7 days) for a stable B2B base where checkout latency
  matters more than registration freshness.

The TTL **does not** apply to:

- The `validate` REST endpoint or the `byte8:vat:validate` CLI — both
  always hit the upstream synchronously.
- The customer-save observer — also synchronous, since the customer is
  watching the form.

It only governs the `validateCached` API and the
`Observer\ValidateQuoteAddress` / `Observer\ValidateBeforeOrderPlace`
checkout paths.

## Requester credentials — best practice

- **Always** set requester country + VAT — without them, VIES returns
  a weaker response with no `requestIdentifier`, and German merchants
  lose their `§18 UStG` proof. HMRC also won't issue a
  `consultationNumber` without a valid requester VRN.
- **Digits only** for the VAT number. The HMRC client strips non-digits
  and only sends the value if it normalises to 9 or 12 digits — a
  `GB`-prefixed entry is silently dropped from the HMRC call (logged
  as a warning).
- **Disable Validate on Checkout** only if you have an enterprise ERP
  that owns customer-group assignment. The customer-save observer
  alone catches registrations.
