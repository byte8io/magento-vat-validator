# Byte8 VAT Validator — Documentation Site

Docusaurus 3 site for [`byte8/module-vat-validator`](../README.md).

Hosted at **https://docs.byte8.io/vat/** — served under the unified Byte8 docs domain via Cloudflare Pages and a path-based Worker router (see `apps/docs-router/` in the byte8.io monorepo).

## Local development

```bash
cd docs
nvm use            # picks up .nvmrc → Node 22
pnpm install
pnpm start
```

Opens at `http://localhost:3000/vat/` (the `baseUrl` prefix is honoured in dev too).

## Production build

```bash
pnpm build
pnpm serve
```

Output lands in `build/`. Deployed via **Cloudflare Pages**:

- **Project:** `docs-magento-vat-validator`
- **Build command:** `pnpm install --frozen-lockfile && pnpm build`
- **Build output:** `build`
- **Root directory:** `docs` (since this Docusaurus project sits in a subfolder of the module repo)
- **Production URL:** `https://docs.byte8.io/vat/`

Cloudflare Pages auto-builds on every push to `main`.

## Theme + branding

The site uses the Byte8 marketing dark aesthetic — deep near-black
background (`#0a0b0e`), Sage-Accounting green accent (`#4ade80`),
soft rounded surface panels with hairline borders, pill buttons.

Theme overrides live in `src/css/custom.css`. The homepage is a
custom React page (`src/pages/index.tsx`) modeled on the byte8.io
marketing product cards.

## Adding a doc page

1. Create the markdown file under `docs/<category>/<slug>.md`
2. Add front-matter:

```yaml
---
sidebar_position: 1
title: Page title
description: One-sentence summary used by search and social cards.
---
```

3. Add the slug to `sidebars.ts` if it's not auto-discovered

## Adding a release note

Drop a new file in `blog/` named `YYYY-MM-DD-<slug>.md` with
front-matter:

```yaml
---
slug: v0-2-0-release
title: v0.2.0 — what shipped
authors: [byte8]
tags: [release]
---
```

Authors are defined in `blog/authors.yml`.
