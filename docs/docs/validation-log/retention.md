---
sidebar_position: 4
title: Retention & prune cron
description: Keep the audit log compliant without unbounded growth.
---

# Retention & prune cron

The validation log table grows monotonically — by default the module
ships with a nightly prune cron to keep it bounded.

## Cron schedule

| Job | Schedule | What it does |
|---|---|---|
| `byte8_vat_validator_prune_log` | `17 3 * * *` (03:17 nightly) | Deletes rows where `requested_at < now() - retention_years` |

## Configuration

**Stores → Configuration → Byte8 → VAT Number Validator → Validation Log**

| Field | Default | Notes |
|---|---|---|
| **Persist Validations to DB** | Yes | Master switch for the audit log |
| **Retention (years)** | 10 | Matches §147 AO. Drop to 7 if you're outside DACH and want a shorter window. |

## Why 10 years?

German tax law (`§147 AO`) requires merchants to retain records
supporting tax treatment for 10 years. UK / Irish equivalents are
typically 6 years. We default to 10 because:

- It's the longest mandatory retention in any market the module
  validates for
- A merchant who later opens a German storefront doesn't have to
  retroactively re-create destroyed audit records

## Disabling the cron

Set retention to a very high number (e.g. 999) to effectively disable
pruning. Don't set it to 0 — that would delete everything immediately
on next cron run.

If you genuinely don't want the prune cron firing, comment out the job
in `etc/crontab.xml` in your own theme/customisation module — but at
that point you also lose the audit log entirely, so it's rarely the
right call.
