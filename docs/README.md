# Byte8 VAT Validator — Documentation Site

Docusaurus 3 site for [`byte8/module-vat-validator`](../README.md).

Hosted at **https://magento-vat-validator.byte8.dev**.

## Local development

```bash
cd docs
nvm use            # picks up .nvmrc → Node 20
pnpm install
pnpm start
```

Opens at `http://localhost:3000/`.

## Production build

```bash
pnpm build
pnpm serve
```

Output lands in `build/`.

## Deploying

Automated. Every push to `main` that touches `docs/**` triggers
`.github/workflows/deploy-docs.yml`, which builds the site and
publishes it to GitHub Pages.

You can also kick a deploy manually from the **Actions** tab on
GitHub → "Deploy Docs" → "Run workflow".

### One-time GitHub setup

1. Go to **Settings → Pages** on the
   [byte8io/magento-vat-validator](https://github.com/byte8io/magento-vat-validator)
   repo
2. Under **Source**, choose **GitHub Actions** (not "Deploy from a branch")
3. First push to `main` (or manual workflow_dispatch) will publish the site
   and the URL will appear under Settings → Pages

No deploy key, no PAT, no secrets needed — `actions/deploy-pages@v4`
uses GitHub's per-workflow OIDC token.

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
