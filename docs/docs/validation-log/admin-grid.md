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
| Status | select | `valid` / `invalid` only — transient errors aren't logged. Renders as a colour-coded pill (green / red / amber) via `Ui\Component\Listing\Column\StatusPill` |
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

## Sales order grid + order view

v1.0 also adds VAT signals to the standard sales surfaces:

- **Sales order grid** — a "VAT Status" column joined from
  `sales_order_address.vat_is_valid`, batch-fetched (no N+1).
  Renders as a colour-coded pill in the same style language as the
  validation log.
- **Sales order view** — a "VAT Validation" panel inside the order's
  `order_additional_info` section showing status badge, source
  (HMRC / VIES / UID-CHE), §18 UStG consultation reference, the
  registered company name + address, and last-checked timestamp.
  Backed by `Block\Adminhtml\Order\View\VatStatus` joining the audit
  log to the order by `(country_code, vat_number)`.

Both surfaces are read-only views of the same audit log — useful for
support agents who need to confirm a buyer's VAT status without
navigating to the log grid.
