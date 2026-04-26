---
sidebar_position: 2
title: Admin grid
description: Stores → Byte8 → VAT Validation Log.
---

# Admin grid

Lives at **Stores → Byte8 → VAT Validation Log**, gated by the
`Byte8_VatValidator::log_view` ACL resource.

## Columns

| Column | Filter | Notes |
|---|---|---|
| ID | text-range | |
| Validated At | date-range | Default sort: descending |
| Country | text | Two-letter ISO |
| VAT Number | text | Without country prefix |
| Status | select | `valid` / `invalid` only — transient errors aren't logged |
| Source | select | `vies` / `hmrc` / `uid_che` |
| Company Name | text | As returned by the upstream |
| Customer Email | text | Snapshot at time of validation |
| Qualified Confirmation Ref | text | The `requestIdentifier` you'd cite in a §18 UStG audit |

## Bookmarks

The grid uses Magento's standard `bookmarks` UI component — save filter
+ column views per admin user (e.g. "Only invalid this month",
"DE customers only").

## Permissions

Two separate ACL resources let you split read access from export access:

| Resource | Grants |
|---|---|
| `Byte8_VatValidator::log_view` | View the grid + open individual rows |
| `Byte8_VatValidator::log_export` | Use the CSV / Excel XML export buttons |

Junior bookkeepers typically need view but not export.
