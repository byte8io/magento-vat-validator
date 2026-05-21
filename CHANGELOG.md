# Changelog

All notable changes to `byte8/module-vat-validator` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0](https://github.com/byte8io/magento-vat-validator/compare/v1.0.0...v1.1.0) (2026-05-21)


### Features

* **activation:** gate validation behind byte8 activation key ([52d8c27](https://github.com/byte8io/magento-vat-validator/commit/52d8c27e91c690c6d69c5ed08f0094e6e2a6c311))
* **nav:** add "← All docs" link to navbar pointing to docs hub ([f3e4aec](https://github.com/byte8io/magento-vat-validator/commit/f3e4aec1a9460ac34025d523b7c3e02beb54d398))
* **search:** wire Algolia DocSearch — cross-product search across docs.byte8.io ([e63e111](https://github.com/byte8io/magento-vat-validator/commit/e63e111cf8746014e1d5c934cd380e931624daef))


### Bug Fixes

* **docs:** lock landing page stats strip to fixed 4-up so the last card stops wrapping ([2b6022d](https://github.com/byte8io/magento-vat-validator/commit/2b6022d7859c27e5f915cb1f598224b99b815e79))
* **search:** force full nav for cross-site search results ([193b957](https://github.com/byte8io/magento-vat-validator/commit/193b9575142d07dfb31715a611055a563a69b23f))
* **search:** keep DocSearch links internal-looking for SEO + clientModule for same-tab nav ([c67a62a](https://github.com/byte8io/magento-vat-validator/commit/c67a62a7c32759694aa1356052d5edcb9b0561db))


### Documentation

* migrate to docs.byte8.io/vat unified domain ([56c4590](https://github.com/byte8io/magento-vat-validator/commit/56c4590f44580918744eb2185d43b05ce03d59d0))

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
