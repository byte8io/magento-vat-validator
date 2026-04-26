---
sidebar_position: 3
title: Upstream toggles
description: Enable / disable VIES, HMRC, and UID-CHE independently.
---

# Upstream toggles

Each upstream has its own admin group with an independent enable toggle
and an endpoint override.

## Why disable an upstream?

- You're a UK-only store: disable VIES + UID-CHE to skip outbound calls
  for non-GB country prefixes
- You're testing against the upstream's sandbox: override the endpoint
  to the test URL (e.g. `uid-wse-a.admin.ch` for Switzerland)
- The upstream changes its endpoint path: override without waiting for a
  module release

## Endpoint defaults

| Upstream | Endpoint |
|---|---|
| VIES | `https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number` |
| HMRC | `https://api.service.hmrc.gov.uk/organisations/vat/check-vat-number/lookup` |
| UID-CHE | `https://www.uid-wse.admin.ch/V5.0/PublicServices.svc` |

## Routing logic

The unified `VatValidator::validate()` routes by country prefix:

| Prefix | Upstream |
|---|---|
| `GB` | HMRC |
| `CH` / `CHE` | UID-CHE |
| Any of the 27 EU member states + `XI` | VIES |
| Anything else | Returns `skipped` with a "no validator available" message |

If the matching upstream is disabled, the validator returns `skipped`
rather than falling through to a different one.
