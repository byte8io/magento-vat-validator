# Changelog

All notable changes to `byte8/module-vat-validator` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Targeting v0.2.0 once Phase 2 verification (live smoke tests against a
real Magento sandbox + real VIES / HMRC / UID-CHE endpoints) is complete.

### Added

- **DB-backed validation log** (`byte8_vat_validator_log`) with admin grid,
  CSV / Excel XML export, and configurable retention defaulting to 10
  years to match `§147 AO`.
- **Persist observer** subscribed to `byte8_vat_validator_validated` —
  every `valid` / `invalid` outcome is written; `unavailable` / `skipped`
  are not, keeping the audit log clean.
- **Nightly retention cron** `byte8_vat_validator_prune_log` (`17 3 * * *`).
- **Two ACL resources**: `Byte8_VatValidator::log_view` and
  `Byte8_VatValidator::log_export`, so view access can be granted without
  granting export.
- **Swiss UID-Register validator** (`UidCheClient`) — hand-rolled SOAP
  envelope against `uid-wse.admin.ch`, no `ext-soap` dependency. Returns
  `valid` only when the organisation is active **and** VAT-registered
  (MWST). New `SOURCE_UID_CHE` constant.
- **`CHE` 3-letter prefix** handled in `VatValidator::normalise()`.
- **`byte8_vat_validator_validated` event** dispatched on every validate
  call so other Byte8 modules (notably Byte8 Ledger) can subscribe
  without coupling.
- **Bulk re-validate CLI** `bin/magento byte8:vat:revalidate-all` with
  `--country`, `--status`, `--since`, `--limit`, `--dry-run` — back-fill
  validations for an existing customer base or run periodic clean-ups.
- **Privacy / GDPR section** in README covering Art. 6(1)(c) lawful
  basis, suggested privacy-policy copy, and data-subject erasure
  guidance.

### Changed

- `source` column on `byte8_vat_validator_log` widened from `varchar(8)`
  to `varchar(16)` to accommodate `uid_che` plus headroom. Pre-v0.1.0
  schema is not yet frozen — this is a free change. From v0.1.0 onwards,
  any column change requires an UpgradeSchema patch.

## [0.1.0] — Unreleased

Initial release.

### Added

- EU VIES REST validation (no SOAP).
- UK HMRC public lookup (no OAuth).
- Auto-assign customer group by validation outcome (domestic /
  intra-EU valid / invalid).
- Per-request in-memory cache so a single checkout doesn't hit VIES
  multiple times.
- REST endpoint `GET /V1/byte8-vat-validator/validate/:cc/:vn`.
- CLI `bin/magento byte8:vat:validate`.
- Dedicated log file `var/log/vat_validator.log`.
- `unavailable` upstream results never strip a customer's existing
  group — guard against flaky VIES degrading active B2B customers.
