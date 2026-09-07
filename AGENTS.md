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

- Laravel side follows PSR-12, thin controllers → services, FormRequest validation, config() over env() in runtime code.
- Frontend is hand-rolled Tailwind + lucide-vue-next. Do NOT add radix/shadcn deps — `frontend/package.json` carries unused ones slated for removal. Design tokens live in `BRANDING.md`; the admin palette (slate + #f5a623) is the operative one for `/admin`.
- All external/article content is untrusted: keep prompt-injection guards in generation and DOMPurify/`sanitize.ts` on article HTML intact.
- `news_topics.generation_status` state machine: `pending → generating → generated/failed`. Queue jobs only process `pending` topics — any new dispatch path must reset status first or it silently no-ops.
- Don't edit `database/migrations/*` — some are already applied in deployed environments. Add new migrations instead.
- cold-email-service keeps state in `data/*.json` (gitignored) — never commit PII there. `leads/*.csv` and any lead list are real personal data: never commit new ones, never print contents into logs.

## Known open items (as of 2026-09-07)

Verified findings awaiting work — check before re-investigating:

- **CRITICAL**: cold-email-service mutating endpoints have no auth; ZeptoMail webhook has no signature verification (`cold-email-service/src/index.js`)
- **HIGH**: DRY_RUN in cold-email-service still calls `markSent()` (pollutes `data/sent.json`); no CI exists; zero tests for the core pipeline or cold-email-service; lock-free JSON state writes (`data/*.json` read-modify-write races)
- **MEDIUM**: Brave key-missing can abort a whole discovery run (no try/catch in `fetchFromSource`); compose env-forwarding gaps vs `.env.example`; Umami/AdSense are not gated by the cookie-consent choice; `frontend/server/routes/feed.xml.ts` has no error handling; `docker/frontend/Dockerfile` uses `npm install` instead of `npm ci`; `data/events.json` grows unbounded and holds recipient PII; `leads/leads-batch1.csv` carries the same PII and is git-tracked (deliberate: machine-consumed — do not publish this repo without purging it too)
- **Design debt**: admin console uses raw slate/`#f5a623` literals instead of the `tailwind.css` HSL tokens; dead dark-mode palette with no toggle; `--ring` token defined but focus rings never applied (WCAG 2.4.7 fail); BRANDING.md is accurate for the public site's tokens/fonts only — the admin is a deliberate second surface

Fixed 2026-09-06/07 (do not re-investigate): SSR sanitize scheme allowlist + whitespace-control-char normalization; article 404 via `setResponseStatus` (styled template kept); admin middleware validates token via `GET /api/v1/admin/auth/me`; discovery `scope` request param (india|global, 422 otherwise); non-destructive regenerate (saveGeneratedArticle updates in place by unique topic_id — 4 feature tests in `tests/Feature/AdminFixesTest.php`); warmup ramp anchored to `START_DATE` (NaN-guarded) or earliest `byDate` state entry; `ads.txt` reads config not env.

Clutter removed 2026-09-06 (user-approved): scaffold tests + `stubs/`, `frontend/components/GridLayout.vue`, `frontend/lib/utils.ts`, unused radix/cva/tailwind-merge deps, `nexus-analytics-dashboard-DESIGN.md`, `lead-list-batch1.md` (PII — also purged from all git history via git-filter-repo the same day; the local `origin` remote config was removed by filter-repo and re-added manually), `PRODUCTION_READINESS.md`, `opencode.json` + `.opencode/`, the Blade/Vite starter stack (welcome page, root package.json, vite.config.js), dead `User::hasPermission()/hasAnyPermission()`, legacy `news-engine.categories` config key, deprecated `resolveSources()`. Generated `public/sitemaps/` are now untracked. Role/Permission models stay — the admin user controller still assigns roles.