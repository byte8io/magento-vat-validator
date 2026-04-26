---
sidebar_position: 2
title: HMRC (UK)
description: UK VAT validation via the HMRC public lookup endpoint.
---

# HMRC — UK VAT validation

Post-Brexit, GB VAT numbers are not in VIES. We hit HMRC's public
unauthenticated lookup endpoint instead.

## What we use

- **Endpoint:** `https://api.service.hmrc.gov.uk/organisations/vat/check-vat-number/lookup/{vatNumber}[/{requesterVatNumber}]`
- **Method:** `GET`
- **Header:** `Accept: application/vnd.hmrc.2.0+json`
- **No OAuth** — the lookup endpoint is public; OAuth is only required
  for the agent / submission APIs

## Response shape

```json
{
  "target": {
    "name": "Acme Ltd",
    "vatNumber": "123456789",
    "address": {
      "line1": "1 Main St",
      "postcode": "SW1A 1AA",
      "countryCode": "GB"
    }
  },
  "processingDate": "2026-04-25T09:00:00Z",
  "consultationNumber": "CONS-2026-1234"
}
```

We surface `consultationNumber` as the equivalent of VIES's
`requestIdentifier` — it's the audit reference HMRC issues when you
include your own VAT number in the call.

## Returning the consultation number

Set **Requester VAT Number** in the General config. The module appends
your VAT number as a path segment; HMRC then returns
`consultationNumber` in the response. Without it, you get the validation
result but no audit reference.

## 404 = invalid

HMRC returns HTTP 404 for unknown VAT numbers (rather than a 200 with
`isValid: false` like VIES). The module maps 404 → `STATUS_INVALID`
with a clear message ("HMRC: VAT number not found") so the audit log
remains consistent across upstreams.

## Header version

Default is `vnd.hmrc.2.0+json`. If HMRC publishes a new major version,
override the endpoint in admin config to point at the new path while you
wait for a module release.
