# World Graph Studio — Headless Frontend (optional)

A minimal Next.js App Router frontend for the WordPress site, modeled on
[9d8dev/next-wp](https://github.com/9d8dev/next-wp). It is entirely optional —
the WordPress site and `worldgraph` plugin work fully without it.

## What's here

- `lib/wordpress.ts` — typed wrapper around the WP REST API (`/wp-json/wp/v2`)
- `lib/worldgraph-admin.ts` — server-only admin wrapper for protected World Graph endpoints (`/wp-json/worldgraph/v1`)
- `app/` — homepage, posts list/detail, and a `/api/revalidate` webhook route
- `app/connections` — headless ComfyUI catalog manager (sync, prepare, materialize, download)
- `site.config.ts` / `menu.config.ts` — site metadata and nav links

## Setup

Container-first workflow (recommended): use the Lando services that already
ship with Node/npm for this project. Do not install or upgrade Node on the host
unless you are intentionally running headless standalone.

```bash
cd headless
cp .env.example .env.local   # point WORDPRESS_URL at your Lando site
```

For the headless Connections manager, also configure admin credentials using a
WordPress Application Password:

```bash
WORLDGRAPH_ADMIN_USER="admin-username"
WORLDGRAPH_ADMIN_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
```

These are consumed server-side by Next API routes under
`/api/worldgraph/connections/*` and are never sent to the browser.

Via Lando (recommended, matches the rest of the stack):

```bash
lando start                # the "headless" service auto-installs deps and runs `next dev`
lando headless-build       # production build, when needed
lando exec cli -- sh -lc 'cd /app/headless && npm run build'
```

The dev server is proxied at `https://headless.worldgraph.lndo.site`.

Standalone (optional, outside Lando):

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
