---
sidebar_position: 2
title: Async queue
description: Non-blocking checkout via the byte8.vat.revalidate queue.
---

# Async queue

Since v1.0 the checkout-time validation path is **non-blocking**:
synchronous calls to VIES / HMRC / UID-CHE have been removed from the
quote-address observer. Instead, the observer reads the most recent
persisted result from the audit log (within the configured
[Result Cache TTL](/docs/configuration/general#result-cache-ttl)) and,
on a cache miss, publishes to the `byte8.vat.revalidate` queue. A
background consumer drains the queue and writes a fresh row to
`byte8_vat_validator_log` — order placement is never blocked on
upstream availability.

:::warning Run the consumer in production
Without a running consumer, the audit log will not refresh after the
first cache miss. Existing checkouts will not break, but reverse-charge
group assignment will lag until the consumer drains.

```bash
bin/magento queue:consumers:start byte8.vat.revalidate
```

In production, supervise this under your existing consumer manager
(systemd, Kubernetes, `cron_run` mode in `app/etc/env.php`, or whatever
you use for `inventory.reservations.cleanup` and friends).
:::

## Why async

Pre-v1.0 the `sales_quote_address_save_before` observer called VIES
synchronously. Two problems:

1. **Stalled checkout on a slow upstream.** A VIES that took 4–5 s to
   respond stalled the checkout's address-save AJAX every step.
   Customers retried, fired the observer again, and burned the HMRC
   rate-limit budget.
2. **A network hiccup blocked order placement.** Five-second timeout
   × 3-5 quote-address saves per checkout step = ten seconds of
   blocking on every shipping-method change.

The new architecture decouples **what tax to apply right now** (read
from the persisted result, fast and local) from **when to refresh
that result** (queue a background job, async).

## How it flows

```
sales_quote_address_save_before
        ↓
ValidateQuoteAddress::execute()
        ↓
FormatValidator::check()  ← cheap, network-free pre-check
        ↓ (if format error)
        return STATUS_INVALID, dispatch validate event,
        surface notice, checkout proceeds (full VAT applied)
        ↓ (if format OK)
ValidationLogRepository::getLatestFresh(country, vat, ttl)
        ↓
   ┌────┴────┐
   │ HIT     │ MISS
   ▼         ▼
apply       RevalidationPublisher::publish() → DB queue
group +     (with 30s Magento-cache dedupe marker)
notice              ↓
                ─── consumer pulls ───
                    ↓
            RevalidationConsumer::process()
                    ↓
            VatValidator::validate() → upstream
                    ↓
            byte8_vat_validator_validated event
                    ↓
            PersistValidationLog (writes audit row)
                    ↓
            ValidationLogRepository::enrichLatest()
                    ↓ (back-fills customer_id / email
                       from the quote when guest checkout
                       didn't provide them at write time)
```

The next quote-address save from the same buyer hits the cache, applies
the right group, and the customer sees the correct tax line.

## Where the messages live

The queue uses Magento's **DB-backed connection** by default — no AMQP
/ RabbitMQ broker required, but you can switch to one in
`etc/queue_topology.xml` if your stack already runs Rabbit or AWS SQS.

| Resource | Name | Purpose |
|---|---|---|
| Topic | `byte8.vat.revalidate` | Routing key |
| Exchange | `byte8.vat.exchange` | Topic exchange (DB connection) |
| Queue | `byte8.vat.revalidate` | The actual message store |
| Consumer | `byte8.vat.revalidate` | The handler binding |

The DTO on the wire is `Byte8\VatValidator\Api\Data\RevalidationRequestInterface`
— country code, VAT number, store id, customer id, quote id. The
consumer routes back through `VatValidator::validate()` so persistence
happens via the standard `byte8_vat_validator_validated` event chain
(no special-case write path).

## Dedupe — two layers

A single Magento checkout step fires `sales_quote_address_save_before`
3-5 times. Without dedupe we'd queue 3-5 identical revalidation jobs
per step.

1. **Publisher-side (30 s).** `RevalidationPublisher` writes a marker
   to Magento's default cache keyed on `(country, vat)` before
   publishing. A second publish within 30 s sees the marker and
   short-circuits — the upstream call is queued exactly once per buyer
   per step.
2. **Consumer-side (TTL-bounded).** `RevalidationConsumer` re-checks
   the validation log before calling the upstream. If a fresh row was
   written since the message was queued (e.g. by a parallel
   interactive request), the consumer drops the job — no duplicate
   HMRC quota burn.

Combined with the 60-second dedupe in `Observer\PersistValidationLog`,
a single buyer attempt produces **one** audit row, not 3–5.

## Async log enrichment

When a guest places an order, the customer email and ID aren't always
on the quote at the moment `ValidateQuoteAddress` fires (they're
filled in later in the checkout flow). The validation log row is
written with `customer_id = NULL`, `customer_email = NULL`.

The consumer fixes this:

1. Loads the quote via `CartRepositoryInterface` using the `quote_id`
   from the queue payload.
2. Extracts the email (`quote.customer_email` →
   `billing.email` → `shipping.email`).
3. Falls back to `CustomerRepositoryInterface::getById($customerId)`
   for logged-in buyers.
4. Calls `ValidationLogRepository::enrichLatest()`, which uses
   `COALESCE(col, ?)` semantics — it only writes when the column is
   currently `NULL`, so it never overwrites a value that an
   interactive request attached correctly.

The result: every `byte8_vat_validator_log` row eventually carries the
buyer's email + customer_id, even for guest checkouts.

:::note Historical rows
Rows written before 2026-04-28 (for guest checkouts) may still have
`customer_email = NULL`. New rows are enriched. If §147 AO audit
hygiene requires retro-attaching email/customer_id from
`sales_order_address`, a one-shot CLI to back-fill them can be added
on request.
:::

## Format errors stay synchronous

Network-free format errors (wrong digit count, wrong charset) **never**
reach the queue. `Model\FormatValidator` catches them inside the
quote-address observer:

- GB: 9 or 12 digits
- CH: 9 digits (after the optional `CHE-` / `CH` prefix is stripped)
- EU/VIES: 4–14 alphanumeric chars per VIES's own contract

A format error returns `STATUS_INVALID` synchronously, dispatches the
standard validate event so the audit log records it, surfaces a
storefront notice, and lets the checkout proceed — the customer pays
full VAT until they fix the number. That's the legally-safe default:
a customer who proceeds with an invalid VAT pays full VAT, not zero.

## Operational checklist

- [ ] Consumer is running (`bin/magento queue:consumers:start byte8.vat.revalidate`)
- [ ] Consumer is supervised — restart on crash, log to a known location
- [ ] Magento default cache is reachable (Redis / Valkey / file) so the
      publisher dedupe marker actually persists between requests
- [ ] HMRC OAuth credentials are configured (otherwise the consumer
      drains messages but every GB lookup returns `unavailable`)
- [ ] You're monitoring the validation log for a healthy ratio of
      `valid` to `unavailable` — a spike in `unavailable` means the
      consumer is hitting an upstream outage and the audit log will
      drift until it recovers
