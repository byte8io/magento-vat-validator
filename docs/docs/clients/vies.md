---
sidebar_position: 1
title: VIES (EU)
description: EU-wide VAT validation via the European Commission's REST endpoint.
---

# VIES — EU VAT validation

The European Commission's VIES (VAT Information Exchange System) is the
authoritative source for EU member-state VAT-number validation.

## What we use

- **Endpoint:** `https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number`
- **Method:** `POST`, JSON body, JSON response
- **No `ext-soap`** — this is the modern REST endpoint, not the
  deprecated SOAP one Magento native still hits

## Supported countries

All 27 EU member states plus Northern Ireland (`XI`):

`AT, BE, BG, CY, CZ, DE, DK, EE, EL, ES, FI, FR, HR, HU, IE, IT, LT, LU,
LV, MT, NL, PL, PT, RO, SE, SI, SK, XI`

The module silently rewrites `GR` → `EL` because VIES uses the linguistic
code for Greece, not the ISO code.

## What you get back

```json
{
  "isValid": true,
  "requestDate": "2026-04-25+02:00",
  "userError": "VALID",
  "name": "Acme GmbH",
  "address": "Musterstraße 1\n10115 Berlin",
  "requestIdentifier": "WAPIAAAA..."
}
```

We surface `requestIdentifier` as the most important field for German
merchants — it's the proof of qualified confirmation
(`§18 UStG`) you'd retain for any reverse-charge invoice.

## Why send your own VAT?

Configure **Requester Country Code** + **Requester VAT Number** in the
module's General config. When set, VIES treats the call as a *qualified
confirmation* (`Bestätigungsverfahren`) and returns a stronger
identifier. Without these, the response is weaker and harder to defend
in audit.

## Outage behaviour

VIES has scheduled maintenance windows (typically Monday early-morning
CET) and unscheduled outages. When VIES returns 5xx or times out, the
module returns `unavailable` — and unavailable results **never** strip a
customer's existing customer group. Native Magento downgrades on outage;
we don't.

The checkout path additionally insulates customers from VIES
flakiness: it reads from the result cache rather than calling VIES
synchronously, and a missed cache enqueues an async revalidation. If
VIES is down, the consumer retries on its own schedule and the
customer's checkout still completes (with their previously-cached
group, or full VAT if they're a new buyer). See
[Async queue](/docs/advanced/async-queue).
