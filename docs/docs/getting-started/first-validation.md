---
sidebar_position: 3
title: Your first validation
description: Run a CLI validation and trace it through the audit log.
---

# Your first validation

The fastest way to confirm the install is working end-to-end.

## Run the CLI

```bash
bin/magento byte8:vat:validate GB123456789
```

You'll see something like:

```
Country:   GB
Number:    123456789
Status:    valid
Source:    hmrc
Name:      Acme Ltd
Address:   1 Main St, London, SW1A 1AA, GB
Ref:       CONS-2026-1234
```

`Ref` is the value you'd retain for `§18 UStG` qualified confirmation
purposes (or HMRC's "consultation number" — same idea).

## Confirm it landed in the log

If you've left **Persist Validations to DB** enabled (default Yes):

```sql
SELECT entity_id, requested_at, country_code, vat_number, status, source, request_identifier
FROM byte8_vat_validator_log
ORDER BY entity_id DESC LIMIT 5;
```

Or in admin: **Stores → Byte8 → VAT Validation Log**.

## Now hit the REST endpoint

```bash
curl https://yourshop.test/rest/V1/byte8-vat-validator/validate/GB/123456789 | jq .
```

Same result, JSON-shaped:

```json
{
  "country_code": "GB",
  "vat_number": "123456789",
  "status": "valid",
  "source": "hmrc",
  "name": "Acme Ltd",
  "address": "1 Main St, London, SW1A 1AA, GB",
  "request_identifier": "CONS-2026-1234"
}
```

## Trigger an observer

Place an order as a logged-in B2B customer with a valid intra-EU VAT and
a different billing country to your store. Watch
`var/log/vat_validator.log` and the validation log table — the
`sales_quote_address_save_before` observer fires when the billing address
is saved, and the `customer_group_id` on the quote flips before totals
are recalculated.
