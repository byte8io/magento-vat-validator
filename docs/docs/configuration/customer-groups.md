---
sidebar_position: 2
title: Customer group mapping
description: How "Zero Tax" actually gets applied.
---

# Customer group mapping

The module assigns customers to one of three configurable groups based on
the validation outcome. It does **not** create the groups or the tax
rules — that's standard Magento territory.

## The chain

```
Validation result
    ↓
Module assigns customer group
    ↓
Customer group's tax class
    ↓
Tax rule mapping (customer tax class + product tax class + country)
    ↓
Tax rate
```

Every link must be intact. Native Magento falls back to default behaviour
when any link breaks — and that's almost always why "VIES says valid but
they're still being charged 19%" support tickets happen.

## The three buckets

| Setting | Applied when | Typical use |
|---|---|---|
| **Group for Domestic Valid** | Buyer country == requester country | Still pays domestic VAT |
| **Group for Intra-EU / UK Valid (Zero Tax)** | Buyer country != requester country | Reverse charge / 0% |
| **Group for Invalid** | Upstream returned `invalid` | Fall back to consumer pricing |

When VIES / HMRC / UID-CHE returns `unavailable` (timeout, 5xx, malformed
response) the customer's **current group is preserved** — we never
downgrade based on a flaky upstream.

## Worked example: German store, French B2B buyer

Goal: French B2B buyer with valid VAT pays 0% (intra-EU reverse charge).

1. **Stores → Customer Groups** — create "B2B EU Valid", tax class
   "Reverse Charge B2B"
2. **Stores → Tax Zones and Rates** — create rate `EU Reverse Charge`
   = 0%, country = "All countries"
3. **Stores → Tax Rules** — create rule:
   - Customer Tax Class: **Reverse Charge B2B**
   - Product Tax Class: **Taxable Goods** (whatever your products use)
   - Tax Rate: **EU Reverse Charge**
4. **Module config → Customer Group Mapping** → "Group for Intra-EU / UK
   Valid VAT (Zero Tax)" = **B2B EU Valid**

Place a test order from a French B2B account with a valid French VAT.
The tax line should be 0.

## Disabling auto-assignment

If your ERP owns customer-group assignment and you only want validation +
audit logging, set **Auto-Assign Customer Group** = **No**. The module
will continue validating and persisting to the log, but won't touch
`customer_group_id`.
