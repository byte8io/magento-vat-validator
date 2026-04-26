---
sidebar_position: 5
title: Troubleshooting
description: Common problems and how to fix them.
---

# Troubleshooting

## "VIES says valid, but customer is still being charged 19% VAT"

99% of the time this is the customer-group / tax-rule chain, not the
module. Walk the chain on the
[Customer Group Mapping](/docs/configuration/customer-groups) page and
confirm every link.

Quick checklist:

1. Is **Auto-Assign Customer Group** turned on?
2. Does the configured "Intra-EU / UK Valid" group have a **tax class**
   assigned (not "None")?
3. Is there a **tax rule** mapping that customer tax class + the
   product tax class + the customer's country to 0%?
4. Has the customer logged out and back in after their group changed?
   (Cached groups in the session can mask new assignments.)

## "Status: unavailable" on every call

The upstream is timing out or returning 5xx.

```bash
tail -50 var/log/vat_validator.log
```

Common causes:

- Outbound HTTPS blocked at firewall — VIES, HMRC, and UID-CHE all need
  port 443 outbound
- Timeout too low (default 5 sec; bump to 8–10 if your network has high
  latency to the upstreams)
- Upstream is genuinely down — VIES has scheduled maintenance windows,
  typically Mondays 03:00–06:00 CET

`unavailable` results are **not** persisted to the audit log and **never**
strip a customer's existing customer group.

## Module enabled but nothing happens at checkout

Check that **Validate on Checkout** is **Yes** — it's a separate toggle
from the master enable.

If still nothing:

```bash
tail -f var/log/vat_validator.log
```

…then trigger a checkout. If you see no log entries, check that the
billing address has both `country_id` and `vat_id` set.

## CSV export button is greyed out

The export buttons are gated by the `Byte8_VatValidator::log_export`
ACL resource. Edit the admin role under **System → Permissions → User
Roles** and grant access.

## Schema upgrade error after install

If `setup:upgrade` fails on the `byte8_vat_validator_log` table, check:

- Magento DB user has `CREATE TABLE` and `ALTER TABLE` privileges on the
  Magento database
- No existing table named `byte8_vat_validator_log` from a previous
  install attempt — drop it manually if so

## Hyvä indicator doesn't appear

- Confirm `Byte8_VatValidatorHyva` is enabled (`bin/magento module:status`)
- Run `bin/magento cache:flush` after install — Hyvä caches layout
  aggressively
- Open the page source and confirm `Byte8_VatValidatorHyva::vat-indicator.phtml`
  is rendered. If not, your theme may have removed the `form.additional.info`
  container — override the module's layout XML to point at your theme's
  equivalent container.

## Still stuck

Open an issue on [GitHub](https://github.com/byte8/module-vat-validator/issues)
with:

- Magento version (`bin/magento --version`)
- Output of `bin/magento module:status | grep Byte8`
- Last 50 lines of `var/log/vat_validator.log`
- The CLI test that's failing: `bin/magento byte8:vat:validate <number>`
