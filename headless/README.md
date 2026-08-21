# World Graph Studio — Headless Frontend (optional)

A minimal Next.js App Router frontend for the WordPress site, modeled on
[9d8dev/next-wp](https://github.com/9d8dev/next-wp). It is entirely optional —
the WordPress site and `worldgraph` plugin work fully without it.

## What's here

- `lib/wordpress.ts` — typed wrapper around the WP REST API (`/wp-json/wp/v2`)
- `app/` — homepage, posts list/detail, and a `/api/revalidate` webhook route
- `site.config.ts` / `menu.config.ts` — site metadata and nav links

## Setup

```bash
cd headless
cp .env.example .env.local   # point WORDPRESS_URL at your Lando site
```

Via Lando (recommended, matches the rest of the stack):

```bash
lando start                # the "headless" service auto-installs deps and runs `next dev`
lando headless-build       # production build, when needed
```

The dev server is proxied at `https://headless.worldgraph.lndo.site`.

Or standalone:

```bash
npm install
npm run dev
```

## Cache revalidation

The optional WordPress module at
`wordpress/wp-content/plugins/worldgraph/plugins/headless-revalidate/` posts a
webhook to `/api/revalidate` whenever posts/pages/categories/tags change,
using the same `WORDPRESS_WEBHOOK_SECRET` configured here and under
Settings → Headless Revalidation in wp-admin.
