---
sidebar_position: 6
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

## Checkout flips back to the wrong group

If the customer-group flip happens at the address form but reverts on
the next checkout step, the cache is stale and the consumer hasn't
caught up. Check:

```bash
# Is the consumer actually running?
ps aux | grep "queue:consumers:start byte8.vat.revalidate"

# Are messages piling up unprocessed?
SELECT COUNT(*) FROM queue_message WHERE topic_name = 'byte8.vat.revalidate';
```

Start the consumer if it isn't running. See
[Async queue](/docs/advanced/async-queue) for the full setup.

## "Status: unavailable" on every GB call

Almost always a misconfigured HMRC OAuth credential.

```bash
tail -50 var/log/vat_validator.log
```

Look for:

- `HMRC validation skipped … no OAuth access token` — Client ID /
  Client Secret missing in admin config.
- `HMRC returned HTTP 401 … bearer token rejected` — credentials are
  set but rejected. Verify the application is **subscribed to "Check
  a UK VAT number" v2.0** in the HMRC Developer Hub and that you're
  using production credentials against the production endpoint (not
  sandbox-against-production or vice versa).
- `HMRC requester VAT is configured but not in 9- or 12-digit format`
  — your **Requester VAT Number** has letters or punctuation. Strip
  to digits only (e.g. `123456789`, not `GB123456789`).

See [HMRC](/docs/clients/hmrc) for the full credential setup.

## "Status: unavailable" on every VIES / UID-CHE call

The upstream is timing out or returning 5xx.

Common causes:

- Outbound HTTPS blocked at firewall — VIES, HMRC, and UID-CHE all need
  port 443 outbound.
- Connect timeout too low — default is 1 s, which is fine for healthy
  networks. Bump to 2–3 s if your hosting has high latency to
  ec.europa.eu / api.service.hmrc.gov.uk.
- Request timeout too low — default is 2 s. Bump to 5 s if you observe
  false `unavailable` outcomes during healthy traffic.
- Upstream is genuinely down — VIES has scheduled maintenance windows,
  typically Mondays 03:00–06:00 CET.

`unavailable` results are **not** persisted to the audit log and **never**
strip a customer's existing customer group.

## Module enabled but nothing happens at checkout

Check that **Validate on Checkout** is **Yes** — it's a separate toggle
from the master enable.

If still nothing, confirm the consumer is running and the address has
both `country_id` and `vat_id`:

```bash
bin/magento queue:consumers:start byte8.vat.revalidate
tail -f var/log/vat_validator.log
```

…then trigger a checkout. The first save publishes to the queue
(if cache-miss); the consumer writes a row; the *next* save reads it
back and applies the group. Force a second save (e.g. switch shipping
method) if you want to see the group flip without restarting.

## "Verify VAT" button doesn't appear on Luma

- The button is rendered under `shippingAddress.before-shipping-method-form`
  in the checkout layout. If your theme has moved the VAT input out
  of that container, override
  `view/frontend/layout/checkout_index_index.xml` to point at your
  theme's container.
- For the registration / account-edit pages, the JS bootstraps off the
  `register-vat-init` x-magento-init block — confirm it's in the
  rendered HTML source. If not, the `customer_account_create` /
  `customer_account_edit` handles in your theme have removed it.
- Cache: `bin/magento cache:flush` after install — Magento aggressively
  caches layout XML.

See [Luma storefront widgets](/docs/frontend/luma) for the full layout
hooks.

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
- Whether the queue consumer is running
- For HMRC issues: confirm the application is subscribed to "Check a
  UK VAT number" v2.0 and you're using the right environment (sandbox
  vs production)
