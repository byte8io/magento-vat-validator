---
sidebar_position: 3
title: CLI commands
description: bin/magento commands for validation and bulk re-validation.
---

# CLI commands

Two commands ship with the module.

## `byte8:vat:validate`

Validate one number — useful for CI smoke tests, diagnosing why a
particular customer fails, and ad-hoc spot checks.

```bash
bin/magento byte8:vat:validate GB123456789
bin/magento byte8:vat:validate DE123456789
bin/magento byte8:vat:validate CHE-123.456.789
```

Exit code `0` on `valid`, `1` otherwise. Output:

```
Country:   GB
Number:    123456789
Status:    valid
Source:    hmrc
Name:      Acme Ltd
Address:   1 Main St, London, SW1A 1AA, GB
Ref:       CONS-2026-1234
```

## `byte8:vat:revalidate-all`

Bulk re-validate every customer address that has a VAT number set. Use
right after install to back-fill validations for an existing B2B
customer base, or as a periodic clean-up.

```bash
bin/magento byte8:vat:revalidate-all                       # all addresses with vat_id
bin/magento byte8:vat:revalidate-all --country=DE,AT       # DACH only
bin/magento byte8:vat:revalidate-all --since=2026-01-01    # updated on/after this date
bin/magento byte8:vat:revalidate-all --limit=50            # spot-check
bin/magento byte8:vat:revalidate-all --dry-run             # list, don't call
```

| Flag | Purpose |
|---|---|
| `--country=DE,AT,CH` | ISO filter, comma-separated |
| `--status=...` | Reserved for future "re-check unavailables only" semantics |
| `--since=2026-01-01` | Only addresses updated on/after this date |
| `--limit=50` | Cap for spot-checks |
| `--dry-run` | List without calling upstreams |

Each result is persisted to `byte8_vat_validator_log` automatically via
the standard `byte8_vat_validator_validated` event — no special-case
write path. Customer ID on the log row will be `null` for CLI runs,
since there's no customer session in CLI context.
