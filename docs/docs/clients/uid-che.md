---
sidebar_position: 3
title: UID-Register (Switzerland)
description: Swiss VAT validation via the federal Bundesamt für Statistik.
---

# UID-Register — Swiss VAT validation

Switzerland is not in VIES (it's not in the EU). The federal Bundesamt
für Statistik (BFS) maintains the UID-Register, which exposes a public
SOAP-based PublicServices endpoint.

## What we use

- **Endpoint:** `https://www.uid-wse.admin.ch/V5.0/PublicServices.svc`
- **Method:** SOAP `GetByUID` with a hand-rolled envelope
- **No `ext-soap`** — we POST the envelope manually via cURL and parse
  with `simplexml_load_string`, preserving the module's "no SOAP
  dependency" promise

## UID format

A Swiss UID is `CHE-123.456.789` (3-letter prefix + 9 digits with optional
dots and dashes). The module accepts any of:

- `CHE-123.456.789`
- `CHE123456789`
- `CH123456789`
- `123456789` (with country dropdown set to CH)

Internally we normalise to country = `CH`, number = `123456789`.

## "Valid" means active AND VAT-registered

A Swiss UID can exist for a non-VAT-registered business (sole traders
below the CHF 100k turnover threshold). For an EU/UK merchant applying
reverse charge, that distinction matters.

The module returns `STATUS_VALID` only when **both**:

- The organisation status is `ACTIVE`, AND
- `vatRegisteredAndActive` is `true` (i.e. the entity has MWST status)

A UID that exists but lacks MWST registration returns `STATUS_INVALID`
with the message `"Swiss UID exists but is not VAT-registered (no MWST)"`.

## Test endpoint

For sandbox testing, override the endpoint to:

```
https://www.uid-wse-a.admin.ch/V5.0/PublicServices.svc
```

(Note the `-a` suffix — that's BFS's "abnahme" / acceptance environment.)
