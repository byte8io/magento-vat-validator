---
sidebar_position: 2
title: VelaFront / Next.js
description: React component + headless hook for VelaFront and any Next.js storefront.
---

# VelaFront / Next.js widget

`@velafront/vat-validator` is a workspace package shipping a drop-in
`<VatInput>` React component and a headless `useVatValidation` hook.

## Install (inside the VelaFront workspace)

The package is a workspace dependency — reference it from any consumer:

```json
{
  "dependencies": {
    "@velafront/vat-validator": "workspace:*"
  }
}
```

## Drop-in component

```tsx
import { VatInput } from '@velafront/vat-validator';

export function CheckoutBillingForm({ country }: { country: string }) {
  return (
    <VatInput
      storeUrl={process.env.NEXT_PUBLIC_MAGENTO_URL!}
      countryCode={country}
      onValidationChange={(valid) => {
        // valid === true | false | null
      }}
    />
  );
}
```

## Headless hook

For full control over the rendering:

```tsx
import { useVatValidation } from '@velafront/vat-validator';

const { result, loading, errored } = useVatValidation(rawInput, {
  storeUrl: process.env.NEXT_PUBLIC_MAGENTO_URL!,
  countryHint: 'DE',
  debounceMs: 600,
});
```

`result` is `null` when:

- Input is too short to be a VAT number
- Upstream returned `unavailable` / `skipped`

That last case is deliberate — the consumer should never render a red
badge based on a slow VIES response.

## Behaviour

- 600 ms debounce by default
- In-flight requests aborted when the user keeps typing — no race conditions
- Recognises 2-letter (`DE…`) and Swiss 3-letter (`CHE…`) prefixes
- `normaliseVatInput()` mirrors the server-side `VatValidator::normalise()`
  — exposed as a standalone export so consumers can pre-validate before hitting the network

## Bundle size

Component + hook + types: under 2 KB gzipped. No dependencies beyond
React and the workspace's existing UI primitives.
