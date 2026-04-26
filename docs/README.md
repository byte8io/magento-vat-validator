# Byte8 VAT Validator — Documentation Site

Docusaurus 3 site for [`byte8/module-vat-validator`](../README.md).

Hosted at **https://byte8.github.io/module-vat-validator/**.

## Local development

```bash
cd packages/modules/module-vat-validator/docs
pnpm install
pnpm start
```

Opens at `http://localhost:3000/module-vat-validator/`.

## Production build

```bash
pnpm build
pnpm serve
```

Output lands in `build/`.

## Deploying

Two options:

### Option 1 — Move docs/ to its own repo

Recommended once the docs site has its own URL and release cadence.
Copy `deploy-docs.yml.example` to `.github/workflows/deploy-docs.yml`
in the new repo, then enable Pages in repo settings (Source: "GitHub
Actions").

### Option 2 — Deploy from the monorepo

If keeping the docs in this repo, copy `deploy-docs.yml.example` to
`.github/workflows/deploy-docs.yml` at the **monorepo root** (not
inside `docs/`). The workflow's `paths:` filter will only fire on
docs changes.

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
