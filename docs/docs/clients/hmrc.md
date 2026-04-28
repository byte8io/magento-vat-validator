---
sidebar_position: 2
title: HMRC (UK)
description: UK VAT validation via HMRC's "Check a UK VAT number" v2.0 API.
---

# HMRC — UK VAT validation

Post-Brexit, GB VAT numbers are not in VIES. The module hits HMRC's
"Check a UK VAT number" v2.0 API instead — an
**application-restricted** OAuth 2.0 endpoint.

:::caution Each merchant must register their own HMRC application
The "Check a UK VAT number" v2.0 API is application-restricted via OAuth
2.0 client_credentials. **Credentials must not be shared between sites
or re-used from a vendor.** HMRC ties consultation references and rate
limits to the calling `client_id`, so every audit-log entry must trace
back to the merchant's own HMRC enrolment.
:::

## What we use

- **Lookup:** `GET https://api.service.hmrc.gov.uk/organisations/vat/check-vat-number/lookup/{vatNumber}[/{requesterVatNumber}]`
- **Token:** `POST https://api.service.hmrc.gov.uk/oauth/token`
  (`grant_type=client_credentials`, scope omitted — the application's
  configured scopes are used implicitly)
- **Auth header on lookup:** `Authorization: Bearer <access_token>`
- **Accept header:** `application/vnd.hmrc.2.0+json`

## Setup — register your HMRC application

1. Sign up at [developer.service.hmrc.gov.uk](https://developer.service.hmrc.gov.uk).
2. Create an application — production *and* sandbox if you want to test
   without hitting live data.
3. Subscribe the application to **"Check a UK VAT number"** v2.0.
4. Copy the application's *Client ID* and *Client Secret* into the
   module's admin fields under **Stores → Configuration → Byte8 → VAT
   Number Validator → UK HMRC**:
   - **HMRC Client ID** — required
   - **HMRC Client Secret** — required (stored encrypted via Magento's
     `Encrypted` backend model)
   - **HMRC OAuth Token Endpoint** — defaults to the production URL.
     Override to `https://test-api.service.hmrc.gov.uk/oauth/token`
     for HMRC's sandbox.
   - **HMRC Lookup Endpoint** — leave default unless HMRC publishes a
     new path.

Without credentials, GB validations return `STATUS_UNAVAILABLE` — this
is non-fatal (checkout proceeds with full VAT applied), but no GB
numbers will ever validate as `valid`.

## Token caching

The module's `HmrcOAuthTokenProvider` requests a bearer token from
`/oauth/token` and caches it in Magento's default cache for
`expires_in − 60s` (the 60-second grace window avoids using a token
that's about to expire mid-request). Subsequent lookups within that
window reuse the cached token — checkout never waits on the token
endpoint.

The token is stored in Magento's standard cache (whatever your store
uses — file / Redis / Valkey). Flushing `config` or the full cache
forces a fresh token fetch.

## Format pre-check

Before the OAuth call, the client enforces HMRC's input rules:

- A VRN must be **9 or 12 digits** after stripping non-digits.
- Anything else returns `STATUS_INVALID` synchronously with the message
  "UK VAT must be 9 or 12 digits (got N)" — no OAuth round-trip, no
  network burn on obvious typos.

The same rule applies to your **Requester VAT Number** (General
config). The client strips non-digits and only sends the requester VRN
if it normalises to 9 or 12 digits — a `GB`-prefixed value or a
malformed entry is logged as a warning and omitted from the lookup
(the lookup still succeeds, but HMRC won't issue a `consultationNumber`).

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

`consultationNumber` is the audit reference HMRC issues when you
include your own VAT number in the call. The module surfaces it as
`request_identifier` on the `ValidationResult`, equivalent to VIES's
`requestIdentifier`.

## Error handling

| HMRC response | Module status | Notes |
|---|---|---|
| `200` with valid body | `valid` | |
| `404 MATCHING_RESOURCE_NOT_FOUND` | `invalid` | Unknown VAT number |
| `400 INVALID_VRN` / other 4xx | `unavailable` | HMRC error code + message preserved on the result |
| `401` / `403` | `unavailable` | Bearer token rejected — verify the app is subscribed to "Check a UK VAT number" v2.0 and the client credentials are correct |
| `5xx` / timeout | `unavailable` | Transient — preserves the customer's existing group |

A 401/403 specifically logs:

> HMRC returned HTTP 401 for GB123456789 — bearer token rejected.
> Verify the application is subscribed to "Check a UK VAT number" v2.0
> and the client_id/client_secret are correct.

## Why we send your own VRN

When **Requester VAT Number** is configured (and it normalises to 9 or
12 digits — see the format pre-check above), HMRC returns a
`consultationNumber` in the response — the equivalent of VIES's
"qualified confirmation". You retain that reference per validation; it
is the audit value HMRC will spot-check against if your tax treatment
of any zero-rated UK B2B sale is questioned.

Without a requester VRN you still get the validation result, but no
audit reference — making it harder to defend the zero-rated treatment
later.
