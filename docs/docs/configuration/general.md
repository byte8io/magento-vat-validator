---
sidebar_position: 1
title: General settings
description: The master switch and per-request behaviour.
---

# General settings

Found at **Stores → Configuration → Byte8 → VAT Number Validator → General**.

| Field | Purpose |
|---|---|
| **Enable VAT Validator** | Master switch. When off, no observers fire and no upstream calls are made. |
| **Validate on Customer Save** | Run validation when a customer / address is saved. |
| **Validate on Checkout** | Re-validate when the quote billing address is saved so the customer-group flip happens before totals are recalculated. |
| **Request Timeout (seconds)** | Hard cap per upstream call. Default 5 — keep ≥ 5 to avoid false negatives. |
| **Requester Country Code** | Your own ISO 2-letter code, sent to VIES so the response includes a legally-valid consultation reference. |
| **Requester VAT Number** | Your own VAT number, digits only. Sent to VIES alongside the requester country. |

## Best practices

- **Always** set requester country + VAT — otherwise VIES returns a
  weaker response without a `requestIdentifier`, and German merchants
  lose their `§18 UStG` proof.
- **Don't** drop the timeout below 5 seconds. VIES can take 4–5 seconds
  during peak hours. A short timeout produces false `unavailable`
  results, which are not persisted to the audit log.
- Disable **Validate on Checkout** only if you have an enterprise ERP
  that owns customer-group assignment — the customer-save observer alone
  catches registrations.
