---
sidebar_position: 4
title: Events for integrators
description: Subscribe to validation outcomes from your own module.
---

# Events for integrators

The module dispatches one event you can subscribe to from your own
Magento module.

## `byte8_vat_validator_validated`

Fired once per call to `VatValidator::validate()`, regardless of
outcome — **including format-only failures and queue-consumer
revalidations**. Carries the full `ValidationResultInterface`
instance.

The event fires from:

- `VatValidator::validate()` — every direct API call (REST
  `/validate`, CLI `byte8:vat:validate`, `validateCached` cache hits
  if you choose to dispatch).
- `Observer\ValidateQuoteAddress` — when a synchronous format error is
  caught (so the audit log records it).
- `Model\Queue\RevalidationConsumer` — every async revalidation that
  actually hits the upstream.

So a subscriber that wants to push validated B2B customers into a
CRM gets the full picture, regardless of which path produced the
result.

```xml
<event name="byte8_vat_validator_validated">
    <observer name="my_module_react_to_vat_validation"
              instance="MyVendor\MyModule\Observer\MyHandler"/>
</event>
```

```php
namespace MyVendor\MyModule\Observer;

use Byte8\VatValidator\Api\Data\ValidationResultInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class MyHandler implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        /** @var ValidationResultInterface $result */
        $result = $observer->getEvent()->getData('result');

        if ($result->getStatus() === ValidationResultInterface::STATUS_VALID) {
            // ... e.g. push to your CRM, ERP, accounting system
        }
    }
}
```

## What the result carries

| Method | Returns |
|---|---|
| `getCountryCode()` | 2-letter ISO |
| `getVatNumber()` | Without country prefix |
| `getStatus()` | `valid`, `invalid`, `unavailable`, or `skipped` |
| `getSource()` | `vies`, `hmrc`, `uid_che`, or `none` |
| `getName()` | Company name from upstream (nullable) |
| `getAddress()` | Company address from upstream (nullable) |
| `getRequestIdentifier()` | Qualified-confirmation reference (nullable) |
| `getMessage()` | Human-readable error / context (nullable) |

## Designed for Byte8 Ledger — and your code too

This event is the integration point [Byte8 Ledger](https://byte8.io/ledger)
will use to push validated B2B customers into Sage / Xero / FreeAgent.
The event hook is in v0.1 because shipping it later would force a
migration. If you build a similar integration, this is your hook.
