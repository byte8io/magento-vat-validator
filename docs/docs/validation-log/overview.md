---
sidebar_position: 1
title: Overview
description: DACH-ready DB-backed audit log of every VAT validation.
---

# Validation log overview

Every successful or failed validation is persisted to a queryable DB
table — `byte8_vat_validator_log` — with the raw upstream payloads,
admin grid, CSV / Excel export, configurable retention, and a separate
ACL resource for export access.

This is the **DACH compliance feature**. German merchants under §147 AO
must retain proof-of-validation records for 10 years; a rotating log file
isn't sufficient for an audit. The DB log is queryable, exportable, and
keyed on customer + invoice context.

## What gets persisted (and what doesn't)

| Status | Persisted? | Why |
|---|---|---|
| `valid` | ✅ | Audit evidence |
| `invalid` | ✅ | Audit evidence — including format-only failures (wrong digit count) caught by `FormatValidator` |
| `unavailable` | ❌ | Transient — would pollute the §147 AO record with hundreds of "we tried, couldn't reach VIES" rows |
| `skipped` | ❌ | Module disabled, unsupported country, or `validateCached` queued an async revalidation — no value in retaining |

Unavailable / skipped attempts still go to `var/log/vat_validator.log`
for ops debugging. They just don't enter the audit table.

## Schema

| Column | Notes |
|---|---|
| `entity_id` | PK |
| `customer_id` | FK to `customer_entity` (`ON DELETE SET NULL`) — preserves the audit row when a customer is deleted |
| `customer_email` | Snapshot at time of validation |
| `store_id` | FK to `store` |
| `country_code` | 2-letter ISO (VIES uses `EL` for Greece) |
| `vat_number` | Without country prefix |
| `status` | `valid` or `invalid` |
| `source` | `vies`, `hmrc`, or `uid_che` |
| `request_identifier` | The qualified-confirmation reference |
| `company_name`, `company_address` | As returned by the upstream |
| `request_payload`, `response_payload` | Raw bytes for audit reconstruction |
| `requested_at` | UTC |

## Indexes

- `(customer_id, requested_at)` — fast per-customer history
- `(country_code, vat_number, requested_at)` — fast TTL-bounded cache
  lookup (`getLatestFresh`). Index-only — the checkout path doesn't
  touch the table heap on a cache hit
- `status` — filter the grid by valid / invalid
- `requested_at` — date-range queries + retention prune

## Disabling persistence

If your accounting flow lives entirely outside Magento (e.g. you sync
everything to Sage / Xero immediately and rely on those records), set
**Persist Validations to DB** = **No** under the Validation Log config
group. The module will continue validating; nothing will be written.

We recommend leaving it on — the table is small (each row ~2 KB), the
nightly prune cron handles retention, and having it means you have an
audit trail when you need it.
