---
sidebar_position: 2
title: Installation
description: Install Byte8 VAT Validator via Composer or manually.
---

# Installation

## Requirements

- Magento 2.4.x
- PHP 8.1+
- No `ext-soap` required
- An [HMRC Developer Hub](https://developer.service.hmrc.gov.uk)
  application with the "Check a UK VAT number" v2.0 API subscribed
  — required only if you validate **GB** VAT numbers. See
  [HMRC](/docs/clients/hmrc) for the full setup.
- A running queue consumer for the
  [async revalidation path](/docs/advanced/async-queue)

## Composer (recommended)

```bash
composer require byte8/module-vat-validator
bin/magento module:enable Byte8_VatValidator
bin/magento setup:upgrade
bin/magento cache:flush
```

This also creates the `byte8_vat_validator_log` table for the audit log.

## Manual install

1. Copy the module files to `app/code/Byte8/VatValidator/`
2. Run:

```bash
bin/magento module:enable Byte8_VatValidator
bin/magento setup:upgrade
bin/magento cache:flush
```

## Run the queue consumer

The checkout path is non-blocking: when the persisted result is stale
or missing, the observer publishes to `byte8.vat.revalidate` instead
of calling the upstream synchronously. You must run the consumer for
those jobs to drain (otherwise the audit log won't refresh):

```bash
bin/magento queue:consumers:start byte8.vat.revalidate
```

In production, supervise this under your existing consumer manager
(systemd, Kubernetes, `cron_run` mode, or whatever you already use for
Magento's stock consumers). The default DB-backed connection is used
— no AMQP / RabbitMQ broker required, but you can switch the
connection in `etc/queue_topology.xml` if you already run one. Full
details on the [Async queue](/docs/advanced/async-queue) page.

## Optional companion modules

| Package | Purpose |
|---|---|
| `byte8/module-vat-validator-hyva` | Live indicator on Hyvä registration / address forms |
| `@velafront/vat-validator` | React component for VelaFront / Next.js storefronts |

The Luma storefront widgets (registration, account-edit, checkout)
ship with the core module — no companion install needed. See
[Luma storefront widgets](/docs/frontend/luma).

## Verify the install

```bash
bin/magento module:status | grep Byte8_VatValidator
bin/magento byte8:vat:validate GB123456789
```
