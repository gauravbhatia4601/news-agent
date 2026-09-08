# AGENTS.md — The Neural Journal

Agent-facing project guide. Read this before doing any work in this repo.

## What this project is

AI-powered news engine ("The Neural Journal"). It discovers topics from Google News RSS (Brave Search fallback), synthesizes multi-source articles with LLMs, quality-gates them, and auto-publishes. Three loosely-coupled subsystems share this repo:

| Subsystem | Location | Stack | Role |
|-----------|----------|-------|------|
| Backend API | repo root | Laravel 12, PHP 8.4, PostgreSQL, Redis, laravel/ai (Prism) | Discovery → generation → publish pipeline + JSON API + admin API |
| Frontend | `frontend/` | Nuxt 4 SSR, Vue 3, Tailwind v3 | Public newspaper site + `/admin` console; proxies all API calls via Nitro catch-all to `NUXT_BACKEND_API_BASE` |
| Cold outreach | `cold-email-service/` | Node/Express (port 4100), ZeptoMail | Fully standalone cold-email microservice — zero integration with the news pipeline; reads `leads/leads-batch1.csv` |

Deployment target: Coolify via `docker-compose.yml` (Postgres/Redis are Coolify-managed, not in compose).

## Key paths

- `routes/console.php` — scheduler (discovery hourly, rankings 15 min, sitemaps 30 min) + `news:*` commands
- `app/News/` — discovery, clustering, generation, publishing (core pipeline)
- `app/News/Services/NewsArticleGenerationService.php` — 624-line orchestrator; AI agents in `app/Ai/`
- `routes/api.php` — all public (`/api/v1/*`, 60/min throttle) + admin (`/api/v1/admin/*`, Sanctum + `EnsureIsAdmin`)
- `frontend/nuxt.config.ts` — proxy config, fonts, Umami/AdSense; `frontend/server/api/[...path].ts` — API proxy
- `config/news-engine.php` + `config/news-engine-global.php` — source queries and generation config
- `.env.example` — every env var documented (local: `cp .env.example .env && php artisan key:generate`)

## Commands

```bash
composer install && npm install            # backend deps (repo root)
cd frontend && npm install                 # frontend
cd cold-email-service && npm install       # outreach service

php artisan test                           # backend suite (sqlite in-memory)
cd frontend && npm run dev                 # Nuxt dev server
composer dev                               # serve + queue:listen + pail + vite concurrently
php artisan news:discover --queue          # run discovery manually
```

Test DB is sqlite in-memory (`phpunit.xml`) — tests need no Postgres/Redis.

## Orchestration contract (ECC agents)

For any non-trivial request, work as ORCHESTRATOR → specialist agents. Define tasks small enough that each is one focused deliverable.

**ORCHESTRATOR** (ECC `ORCHESTRATOR`) owns the plan:

1. Break the request into small tasks (each: one subsystem, one concern, verifiable done-state). Track them in a task list; do them one by one.
2. Delegate per role — never let one agent both design and blindly implement:
   - **ARCHITECT** (`architect`) — before structural changes: design, boundaries, file evidence. Read-only unless explicitly asked to write.
   - **DEVELOPER** (`DEVELOPER`) — implements; smallest working diff; follows the code style already in the touched files.
   - **DESIGNER** (`DESIGNER`) — for any UI change: verify against `BRANDING.md` tokens before and after.
   - **TESTING** (`TESTING`) — after behavior changes; this repo is far below coverage targets, so new behavior ships with a test.
   - **SECURITY** (`security-reviewer`) — mandatory before finishing anything touching auth, webhooks, sanitization, or external APIs.
   - **DOCUMENTATION** (`DOCUMENTATION`) — docs touched only when behavior or setup changes.
3. Collect every delegated result and integrate before ending the turn (no fire-and-forget).
4. Verify: run `php artisan test` after backend changes, `npm run build` in `frontend/` after frontend changes. Report failures verbatim, never claim green without running.

Code-review (`code-reviewer`) runs after any multi-file change. `security-scan` before anything auth/webhook-related ships.

## Rules of this repo

- **Never let a tracked directory become empty** (e.g. `resources/views`) — git drops empty dirs, fresh containers then crash at boot (`view:cache` → Finder.php "directory does not exist" → `set -e` exit). Keep a `.gitkeep`. This caused the Sep 2026 deploy outage: local checks passed because the empty dir still existed on the local disk.
- Laravel side follows PSR-12, thin controllers → services, FormRequest validation, config() over env() in runtime code.
- Frontend is hand-rolled Tailwind + lucide-vue-next (unused radix/shadcn deps were removed 2026-09-06). Design tokens live in `BRANDING.md`; the admin surface uses the `admin-*` token group.
- All external/article content is untrusted: keep prompt-injection guards in generation and DOMPurify/`sanitize.ts` on article HTML intact.
- `news_topics.generation_status` state machine: `pending → generating → generated/failed`. Queue jobs only process `pending` topics — any new dispatch path must reset status first or it silently no-ops.
- Don't edit `database/migrations/*` — some are already applied in deployed environments. Add new migrations instead.
- cold-email-service keeps state in `data/*.json` (gitignored) — never commit PII there. `leads/*.csv` and any lead list are real personal data: never commit new ones, never print contents into logs.

## Known open items (as of 2026-09-08)

All verified issues from the Sep 2026 audit are fixed except the opportunistic items below. Check this list before re-investigating.

**Remaining (low priority):**
- Role/permission migrations still create dead tables (kept — already applied in deployed DBs; a drop migration would finish the RBAC removal)
- Cold-email minor defects: `markSent` spreads stale fields last (inverted merge order), A/B split skews when early leads are already sent, SVG fill/stroke hexes in admin layouts not tokenized
- `NewsArticleGenerationService` is a 624-line orchestrator (maintainability, works fine)
- `index.vue` fires one `getHot` request per top-level category on client nav; `admin/queue.vue` polls every 5s with no visibility-gating
- `leads/leads-batch1.csv` carries real PII and is git-tracked (machine-consumed — never publish this repo without purging it)
- History rewrite + all fix commits were force-pushed to `origin/main` on 2026-09-08; GitHub Support should still be asked to purge cached views of the old PII commit

**Fixed 2026-09-06/07/08 (do not re-investigate):** cold-email auth (`OUTREACH_API_KEY` bearer) + webhook secret (`WEBHOOK_SECRET`) + PII-read gating on GET /, /leads, /stats, /followup/due; dry-run writes no state; atomic JSON writes + events cap (5000); email validation + 64kb body limit + optional CORS; discovery survives failed sources; config drift aligned (8 / 5); compose env-forwarding complete; `npm ci` in frontend Dockerfile; feed XML escaping; /health no exception leak; RBAC stripped end-to-end (models, endpoints, admin UI); consent-gated Umami/AdSense; feed.xml.ts error handling; admin tokens (`--admin-*` × 8) + focus rings + sr-only h1 + skip link; dark palette + JetBrains Mono removed; admin login no longer pre-fills an email; CI via GitHub Actions (Pint+PHPUnit / Nuxt build / node:test); backend tests 21, cold-email tests 21. Deploy outage root-caused 2026-09-08: missing `resources/views` dir (see Rules). Note: entrypoint stays warn-and-generate for APP_KEY (fail-fast reverted — Coolify sets `APP_ENV=local`) and the queue worker stays at `numprocs=1` under the 512m limit.

**Clutter removed 2026-09-06 (user-approved):** scaffold tests + `stubs/`, `frontend/components/GridLayout.vue`, `frontend/lib/utils.ts`, unused radix/cva/tailwind-merge deps, `nexus-analytics-dashboard-DESIGN.md`, `lead-list-batch1.md` (PII — also purged from all git history via git-filter-repo the same day), `PRODUCTION_READINESS.md`, `opencode.json` + `.opencode/`, the Blade/Vite starter stack, dead `User::hasPermission()/hasAnyPermission()`, legacy `news-engine.categories` config key, deprecated `resolveSources()`. Generated `public/sitemaps/` untracked.