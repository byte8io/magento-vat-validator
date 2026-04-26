# Byte8_VatValidator — EU VIES + UK HMRC + Swiss UID VAT Number Validator

A lightweight Magento 2 module that validates B2B buyers' VAT numbers against the
**EU VIES** REST API, the **UK HMRC** public lookup endpoint, and the **Swiss
UID-Register** (Bundesamt für Statistik) — at customer registration and again
at checkout — and automatically moves validated customers into a configurable
"Zero Tax" customer group so the right tax rules fire immediately.

## Why merchants install it

- Magento's native VAT validation hits the deprecated VIES SOAP endpoint and
  offers no UK coverage post-Brexit.
- This module uses the current REST endpoints, works for all 27 EU member
  states plus Northern Ireland (`XI`) and Great Britain (`GB`), and produces a
  typed, cache-aware validation result.
- Validated B2B customers are auto-assigned to your chosen customer group, so
  any existing tax rule driven by customer group (reverse charge, 0% for
  intra-EU B2B, etc.) just works.

## Features

- EU VIES REST validation (no SOAP, no `php-soap` extension required)
- UK HMRC public lookup (no OAuth, no client registration)
- Swiss UID-Register validation via hand-rolled SOAP envelope (no
  `ext-soap` dependency); returns `valid` only when the organisation is
  active **and** VAT-registered (MWST)
- Pluggable: disable any upstream independently
- Auto-assign customer group by outcome (domestic / intra-EU valid / invalid)
- Per-request in-memory cache so one checkout doesn't hit VIES 5 times
- REST endpoint for headless / checkout AJAX:
  `GET /rest/V1/byte8-vat-validator/validate/:countryCode/:vatNumber`
- **DB-backed validation log** with admin grid, CSV / Excel XML export,
  configurable retention (default 10 years per §147 AO) and a nightly prune
  cron — built for German Finanzamt audits but useful for any merchant
- **Event hook** `byte8_vat_validator_validated` so other modules
  (e.g. Byte8 Ledger) can subscribe to every validation
- Dedicated log file at `var/log/vat_validator.log` for ops debugging
- CLI: `bin/magento byte8:vat:validate GB123456789`

## Requirements

- Magento 2.4.x
- PHP 8.1+

## Installation

```bash
composer require byte8/module-vat-validator
bin/magento module:enable Byte8_VatValidator
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configuration

Navigate to **Stores > Configuration > Byte8 > VAT Number Validator**.

### General

| Field | Description |
|-------|-------------|
| Enable VAT Validator | Master switch |
| Validate on Customer Save | Run validation when a customer or address is saved |
| Validate on Checkout | Revalidate when the quote's billing address is saved |
| Request Timeout (seconds) | Hard cap on upstream calls |
| Requester Country Code | Your 2-letter ISO code (e.g. `GB`, `DE`) — sent to VIES |
| Requester VAT Number | Your own VAT number — included in VIES calls for a consultation number |

### EU VIES

Toggle + endpoint override. Defaults to the current EC REST endpoint.

### UK HMRC

Toggle + endpoint override. Defaults to the public HMRC lookup endpoint.

### Customer Group Mapping

| Field | Applied When |
|-------|--------------|
| Auto-Assign Customer Group | Master switch for group assignment |
| Domestic Group | VAT valid AND buyer country == requester country |
| Intra-EU / UK Valid Group | VAT valid AND buyer country != requester country (zero-tax / reverse-charge) |
| Invalid Group | VAT validation returned "invalid" |

When VIES or HMRC is **unavailable** (HTTP 5xx, timeout, malformed
response) the customer's current group is preserved — we never downgrade a
customer based on a flaky upstream.

### Validation Log (DACH compliance: §18 / §147 AO)

| Field | Description |
|-------|-------------|
| Persist Validations to DB | Master switch — when Yes, every `valid` / `invalid` outcome is written to `byte8_vat_validator_log`. Transient `unavailable` errors are NOT persisted, keeping the audit log clean. |
| Retention (years) | Default 10 — matches §147 AO. A nightly cron (`byte8_vat_validator_prune_log` at 03:17) deletes anything older. |

View entries under **Stores → Byte8 → VAT Validation Log**. The grid
supports filters (date range, country, status, source, customer email,
qualified-confirmation reference) and CSV / Excel XML export — the export
button is gated by a separate `Byte8_VatValidator::log_export` ACL so you
can grant view access without granting export.

Each row stores: customer id + email at time of check, store id, country,
VAT number, status, source (`vies` / `hmrc`), the upstream
`requestIdentifier` (your *qualifizierte Bestätigung* reference), the
returned company name + address, and the raw request + response payloads
for audit reconstruction.

## REST endpoint

```bash
curl https://yourshop.test/rest/V1/byte8-vat-validator/validate/GB/123456789
```

Returns a JSON validation result:

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

Status is one of `valid`, `invalid`, `unavailable`, `skipped`.

## CLI

### Validate one number

```bash
bin/magento byte8:vat:validate GB123456789      # UK / HMRC
bin/magento byte8:vat:validate DE123456789      # EU / VIES
bin/magento byte8:vat:validate CHE-123.456.789  # Switzerland / UID-Register
```

Exit code `0` on valid, `1` otherwise — handy in CI / smoke tests.

### Bulk re-validate every B2B customer

Useful right after install (back-fill validations for an existing customer
base) or as a periodic clean-up to catch numbers that became invalid.

```bash
bin/magento byte8:vat:revalidate-all                       # everyone with a vat_id
bin/magento byte8:vat:revalidate-all --country=DE,AT       # DACH only
bin/magento byte8:vat:revalidate-all --since=2026-01-01    # addresses updated since
bin/magento byte8:vat:revalidate-all --limit=50            # spot-check
bin/magento byte8:vat:revalidate-all --dry-run             # list, don't call
```

Each result is persisted to `byte8_vat_validator_log` automatically (no
special-case path — same event hook the live observers use). The command
prints a per-row outcome and a final summary with valid / invalid /
unavailable / skipped counts.

## How the "Zero Tax" rule gets applied

This module does not edit tax rules directly. Instead:

1. Configure a customer group (e.g. "B2B EU Valid") with the tax class you
   want.
2. Add a Magento tax rule that maps that tax class to 0%.
3. Point the module's **Intra-EU / UK Valid Group** setting at the group above.

When validation succeeds during registration, the customer is moved into that
group — and when it succeeds during checkout, the quote's customer group is
updated in-flight so totals recalculate before the order is placed.

## Logging

All validator activity is logged to `var/log/vat_validator.log`. In production,
tail this file to monitor upstream availability and validation outcomes.

## Upsell hook

After install, merchants receive a follow-up email from Byte8 introducing
**Byte8 Ledger**, which syncs validated B2B customers straight into
**Sage** and **Xero**. Cross-sell copy lives in Byte8's email platform, not in
this module.

## Privacy & GDPR

This module processes and stores personal / business data — read this section
before installing on a production store.

### What we send to third parties

| Upstream | Data sent | Where it goes |
|---|---|---|
| EU VIES | The buyer's country code, VAT number, and (if configured) your own country + VAT number as the requester | EC Directorate-General for Taxation and Customs Union (`ec.europa.eu`) |
| UK HMRC | The buyer's VAT number, and (if your requester VAT is configured) your own VAT number | HM Revenue & Customs (`api.service.hmrc.gov.uk`) |
| Swiss UID-Register | The buyer's UID (CHE prefix + 9 digits) | Bundesamt für Statistik (`uid-wse.admin.ch`) |

Both upstreams may return the buyer's company name and registered business
address. We persist these in `byte8_vat_validator_log` only when the
"Persist Validations to DB" setting is enabled.

### Lawful basis (GDPR Art. 6 / UK GDPR Art. 6)

- **Sending VAT numbers to VIES / HMRC:** Art. 6(1)(c) — *legal obligation*.
  EU and UK VAT law requires merchants to verify cross-border B2B VAT
  numbers before applying zero-rated / reverse-charge treatment.
- **Storing the validation log:** Art. 6(1)(c) — *legal obligation*.
  Specifically, German `§147 AO` mandates 10-year retention of records
  supporting tax treatment; equivalent obligations exist in most EU member
  states.

This means **you do not need consent** to use this module for B2B
validation — it falls under your existing tax-compliance obligations. You
**do** need to disclose the processing in your privacy policy. Suggested
copy:

> We verify your VAT identification number against the European
> Commission's VIES database (and, for UK numbers, against HMRC) when you
> register or place an order, in order to apply the correct VAT treatment.
> The validation result, including any company name and address returned
> by VIES / HMRC, is retained for [10] years in line with our statutory
> tax-record obligations.

### Data subject rights

The validation log is keyed on `customer_id` (with `ON DELETE SET NULL`),
so deleting a customer detaches their log entries but preserves the
historical record for audit. If a data subject requests erasure under
GDPR Art. 17, you'll need to weigh that against your Art. 17(3)(b)
exemption for "compliance with a legal obligation". In most cases the
tax-record obligation prevails — document this in your erasure-request
response process.

### What we do NOT do

- We do not phone home. The module makes no calls to byte8.io or any
  Byte8 endpoint.
- We do not log to any third-party telemetry / APM service.
- We do not transmit the buyer's name, email, or address to VIES /
  HMRC — only the country code + VAT number.

## License

MIT — see [LICENSE.txt](LICENSE.txt).
