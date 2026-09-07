# The Neural Journal — Frontend

Nuxt 4 SSR app: public newspaper site + `/admin` console. See the repo root `README.md` and `AGENTS.md` for project context.

## Stack

- Nuxt 4 (SSR), Vue 3, Tailwind CSS v3 (`@nuxtjs/tailwindcss`), lucide-vue-next icons
- All API traffic is same-origin: a Nitro catch-all (`server/api/[...path].ts`) proxies `/api/*` to the Laravel backend at `NUXT_BACKEND_API_BASE`
- `/storage/*`, `/sitemaps/*`, `/robots.txt`, `/feed.xml` are also proxied/server-fetched from the backend

## Setup

```bash
npm install
npm run dev        # dev server (port 3000)
npm run build      # production build (.output/)
```

## Environment

| Variable | Purpose |
|----------|---------|
| `NUXT_BACKEND_API_BASE` | Base URL of the Laravel backend API (proxied behind `/api/*`) |
| `NUXT_PUBLIC_ADSENSE_CLIENT` / `NUXT_PUBLIC_ADSENSE_SLOT` | AdSense (empty = ads hidden) |

## Layout

- `pages/` — public (`/`, `/trending`, `/categories`, `/category/[name]`, `/search`, `/article/[slug]`, legal) + `pages/admin/*` (client-side console)
- `server/api/[...path].ts` — API proxy; `server/routes/` — feed.xml / sitemap.xml passthroughs
- `composables/` — `useNewsApi()` (public), `useAdminApi()` + `useAdminAuth()` (Bearer token in `admin-token` cookie)
- `utils/sanitize.ts` — article HTML sanitization (DOMPurify on client; SSR fallback)
- `assets/css/tailwind.css` + `nuxt.config.ts` — design tokens (see `BRANDING.md` at repo root)