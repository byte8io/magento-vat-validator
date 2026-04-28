---
sidebar_position: 1
title: REST API
description: Anonymous REST endpoints for headless / AJAX validation.
---

# REST API

The module exposes **two** anonymous REST endpoints — same response
shape, different latency / freshness trade-offs. Pick the one that
matches your caller.

## `GET /V1/byte8-vat-validator/validate/:countryCode/:vatNumber`

**Synchronous.** Always hits the upstream (VIES / HMRC / UID-CHE).

Use this from:

- Address-form widgets where the customer just clicked "Verify VAT"
  and is actively waiting for the result.
- CI smoke tests / monitoring probes that need to detect upstream
  outages.
- Any caller that wants the freshest possible answer and is willing
  to wait (and pay the upstream rate-limit cost).

```bash
curl https://yourshop.test/rest/V1/byte8-vat-validator/validate/GB/123456789
```

```json
{
  "country_code": "GB",
  "vat_number": "123456789",
  "status": "valid",
  "source": "hmrc",
  "name": "Acme Ltd",
  "address": "1 Main St, London, SW1A 1AA, GB",
  "request_identifier": "CONS-2026-1234",
  "message": null
}
```

## `GET /V1/byte8-vat-validator/lookup/:countryCode/:vatNumber`

**Cache-aware, non-blocking.** Reads the most recent persisted
validation log entry within the configured
[Result Cache TTL](/docs/configuration/general#result-cache-ttl). On a
cache miss, queues an asynchronous revalidation and returns
`status=skipped` immediately — the consumer revalidates in the
background and writes a fresh row to `byte8_vat_validator_log`.

Use this from:

- Headless storefront checkout (Next.js / VelaFront / custom React) —
  rendering a "checking…" indicator while the consumer drains is far
  better UX than a 2-second hang on every keystroke.
- Server-side checkout observers in custom modules.
- Any latency-sensitive path where you can render a friendly
  placeholder while the queue catches up.

```bash
curl https://yourshop.test/rest/V1/byte8-vat-validator/lookup/GB/123456789
```

Cache hit (returns the cached row):

```json
{
  "country_code": "GB",
  "vat_number": "123456789",
  "status": "valid",
  "source": "hmrc",
  "name": "Acme Ltd",
  "address": "1 Main St, London, SW1A 1AA, GB",
  "request_identifier": "CONS-2026-1234",
  "message": null
}
```

Cache miss (queues async revalidation, returns immediately):

```json
{
  "country_code": "GB",
  "vat_number": "123456789",
  "status": "skipped",
  "source": "none",
  "name": null,
  "address": null,
  "request_identifier": null,
  "message": "Revalidation queued"
}
```

The frontend should treat `status=skipped` with `message="Revalidation
queued"` as "checking…" and either:

- Re-poll `/lookup` after 1–2 s (the consumer typically drains within
  a single tick), **or**
- Fall back to the synchronous `/validate` endpoint if the user is
  blocked on a definitive answer.

:::caution The consumer must be running
The async path requires the `byte8.vat.revalidate` queue consumer:

```bash
bin/magento queue:consumers:start byte8.vat.revalidate
```

Without it, cache misses on `/lookup` return `skipped` indefinitely
and the audit log will not refresh. See [Async queue](/docs/advanced/async-queue)
for the full setup.
:::

## When to pick which

| Caller | Endpoint | Why |
|---|---|---|
| Storefront "Verify VAT" button | `/validate` | Customer pressed a button — they're expecting the wait |
| VelaFront `<VatInput>` debounced field | `/validate` | Debounced 600 ms; freshness > latency for a hands-on form |
| Headless checkout review step | `/lookup` | Fires every quote update; can't burn an upstream call each time |
| Magento native checkout (Luma) | (handled internally) | The `Observer\ValidateQuoteAddress` already uses the cache + queue path — no REST call needed |
| CI smoke test | `/validate` | Wants to detect upstream outages |
| External CRM enrichment job | `/validate` | Bulk job, latency budget is per-row, not per-page |

## Status field

| Value | Meaning |
|---|---|
| `valid` | Upstream confirmed the VAT number is valid |
| `invalid` | Upstream confirmed the VAT number is not valid (or format pre-check failed: e.g. wrong digit count) |
| `unavailable` | Upstream returned 5xx, timed out, or returned malformed JSON / authentication failed |
| `skipped` | Module disabled, country has no validator, input was malformed, **or** `/lookup` queued an async revalidation |

## Source field

`vies` (EU), `hmrc` (UK), `uid_che` (Switzerland), or `none` (skipped).

## Caching layers

There are **two** caches in play and they don't overlap:

1. **Per-request in-memory cache** (`ValidationCache`) — keys on
   `(country, number)` within a single PHP-FPM request. Both
   `/validate` and `/lookup` honour this, so a single request that
   asks twice only hits the upstream once.
2. **DB-backed result cache** (validation log + Result Cache TTL) —
   honoured **only** by `/lookup`. `/validate` always bypasses it and
   hits the upstream.

## Rate limiting

Not enforced by the module. If you're exposing these endpoints to an
untrusted frontend, put a rate limiter (Cloudflare, Varnish, your CDN
of choice) in front of them. HMRC enforces its own per-application
rate limits — `/lookup` is the right choice if you're worried about
hitting them.
