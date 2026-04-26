---
sidebar_position: 3
title: CSV / Excel export
description: Export the log for Finanzamt audit submissions.
---

# CSV / Excel export

The grid toolbar has an "Export" dropdown with two formats:

- **CSV (Finanzamt audit format)** — UTF-8 with BOM, comma-separated
- **Excel XML** — `.xml` file Excel opens directly

Both are produced by Magento's standard `mui/export/gridToCsv` and
`mui/export/gridToXml` controllers — no custom serialisation, no risk of
schema drift between the table and the export.

## Workflow for a Finanzamt audit

1. Filter the grid to the period under audit (e.g. `Validated At` =
   2025-01-01 → 2025-12-31)
2. Optionally narrow by country if the audit scope is country-specific
3. Click **Export → CSV** — the file matches the columns currently
   visible
4. Hand the file to your tax advisor along with the corresponding
   invoices

The `request_identifier` column is the value the auditor will spot-check
against VIES / HMRC / UID-CHE for re-confirmation if they want to.

## Permissions reminder

The export buttons are gated by `Byte8_VatValidator::log_export` ACL.
Grant it only to roles that need it — typically Finance, not Customer
Service.
