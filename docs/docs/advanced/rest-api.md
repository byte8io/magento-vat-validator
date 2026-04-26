---
sidebar_position: 1
title: REST API
description: Anonymous REST endpoint for headless / AJAX validation.
---

# REST API

The module exposes one anonymous REST endpoint that returns the same
`ValidationResult` the internal observers consume.

## Endpoint

```
GET /rest/V1/byte8-vat-validator/validate/:countryCode/:vatNumber
```

Anonymous — no token required. Same code path the live observers use,
so what you see here is exactly what the customer-save / quote-save
observers see.

## Example

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

## Status field

| Value | Meaning |
|---|---|
| `valid` | Upstream confirmed the VAT number is valid |
| `invalid` | Upstream confirmed the VAT number is not valid |
| `unavailable` | Upstream returned 5xx, timed out, or returned malformed JSON |
| `skipped` | Module disabled, country has no validator, or input was malformed |

## Source field

`vies` (EU), `hmrc` (UK), `uid_che` (Switzerland), or `none` (skipped).

## Caching

The same per-request in-memory cache the internal observers use also
applies to REST calls within the same PHP-FPM request. Across separate
requests, no caching is applied — every REST call hits the upstream.

## Rate limiting

Not enforced by the module. If you're exposing this endpoint to an
untrusted frontend, put a rate limiter (Cloudflare, Varnish, Magento
varnish ESI, your CDN of choice) in front of it.
