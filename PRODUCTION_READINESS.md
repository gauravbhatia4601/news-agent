# Production Readiness Audit — Progress Tracker

> Generated: 2026-06-21
> Last Updated: 2026-06-22
> Verdict: **LAUNCHABLE WITH CONDITIONS** — Critical fixes applied, residual risks documented below.

---

## Scorecard (Updated)

| Category | Before | After | Status |
|----------|:---:|:---:|--------|
| Security | 2 | 7 | Fixed |
| Production Readiness | 2 | 7 | Fixed |
| Reliability | 3 | 7 | Fixed |
| Scalability | 3 | 6 | Improved |
| Billing Robustness | 0 | 0 | Deferred (no monetization) |
| Monetization Readiness | 1 | 1 | Deferred (no monetization) |
| AI Safety | 2 | 7 | Fixed |
| Observability | 1 | 6 | Fixed |
| Testing | 1 | 4 | Improved (14 tests, CI pipeline) |
| Compliance/Trust | 1 | 6 | Fixed |

---

## Completed Fixes (30)

### Security (8)
- [x] SEC-01: Rate limiting on login (5/min), public API (60/min), newsletter (5/min), admin actions (30/min)
- [x] SEC-02: Sanctum token expiration 24h (was: never expire)
- [x] SEC-03: Removed `is_admin` from `$fillable`, explicit property assignment
- [x] SEC-04: DOMPurify sanitization on all `v-html` article content (public + admin)
- [x] SEC-05: Token moved to secure, sameSite=strict cookie (no localStorage). Note: not httpOnly — Nuxt useCookie limitation
- [x] SEC-06: `EnsureIsAdmin` checks `is_admin` only (removed `roles()->exists()` blanket)
- [x] SEC-07: Replaced `shell_exec('ps aux')` with cache-based worker heartbeat
- [x] SEC-08: Prevent self-deletion + last-admin deletion in UserController

### AI Safety (6)
- [x] AI-01: Prompt injection guard (source delimiting + untrusted data instruction + control char stripping)
- [x] AI-02: LLM cost tracking via `ai_invocations` table + admin "AI Tracking" page at `/admin/ai-invocations`
- [x] AI-03: Retry cap at 5 attempts, no counter reset on manual retry
- [x] AI-04: `withoutOverlapping(3600)` on both discovery schedules
- [x] AI-05: Per-topic try/catch so one LLM failure doesn't abort the batch
- [x] AI-06: Model fallback configuration (env-driven `NEWS_GENERATION_FALLBACK_PROVIDER/MODEL`)

### Database (5)
- [x] DB-01: Atomic claim on pending topics (`UPDATE...WHERE status='pending'` → `generating`)
- [x] DB-02: `DB::transaction` on `saveTopicWithSources` and `saveGeneratedArticle`
- [x] DB-03: Index on `news_topics.generation_status`
- [x] DB-04: Explicit `$fillable` on NewsArticle, NewsTopic, NewsTopicSource, Category
- [x] DB-05: Fixed duplicate unique-index migration (now DB-agnostic, checks existence)

### Observability (4)
- [x] OBS-01: `AuditLogService::log()` wired into all admin controllers (login/logout/create/update/delete/regenerate/dispatch/trigger)
- [x] OBS-02: Structured JSON logging + daily rotation (14-day retention, `LOG_LEVEL=warning`)
- [x] OBS-04: Deep health check endpoint (DB, Redis, queue depth, worker heartbeat)
- [x] N+1 fix: Added `categoryRelation.parent` eager load to all article list queries

### Compliance (4)
- [x] COMPL-01: Privacy policy rewritten to match actual data collection (emails, IPs, Umami, GDPR rights)
- [x] COMPL-02: Consent record on newsletter subscribers (IP, UA, consent_text, consent_at)
- [x] COMPL-03: Removed duplicate Umami script injection from `app.vue`
- [x] COMPL-04: Data retention jobs (failed jobs 7d, tokens 24h, subscribers 90d, article views 90d)

### Infrastructure (3)
- [x] INFRA-01: `news:backup-db` command with `pg_dump` + gzip + 7-day retention, scheduled daily at 2am
- [x] INFRA-02: Containers run as non-root (`www-data` backend, `app` frontend) + container resource limits
- [x] INFRA-03: Auto-migrate is opt-in via `RUN_MIGRATIONS` env var

### Testing & CI (2)
- [x] INFRA-05: CI pipeline (GitHub Actions: PHP lint + test + Docker build)
- [x] INFRA-06: Critical tests (14 passing: 7 auth tests, 5 newsletter tests, 2 existing)

---

## Residual Risks (Not Fixed — Acceptable for Launch)

| # | Risk | Severity | Why deferred |
|---|------|----------|-------------|
| R-01 | Admin token cookie not httpOnly | Medium | Nuxt `useCookie` can't set httpOnly. Full fix requires backend-set cookie architecture. Mitigated by DOMPurify (XSS prevention) + `sameSite: strict` + `secure: true` + no localStorage. |
| R-02 | No Sentry/error monitoring | Medium | Requires account + DSN. Can be added post-launch. Structured JSON logging is in place. |
| R-03 | `RecomputeRankingsCommand` loads all articles into memory | Medium | Not a problem at current scale (~426 articles). Will need chunking at 10K+ articles. |
| R-04 | No content safety classifier (PII/defamation/copyright) | Medium | User chose to keep auto-publish. Prompt injection guard + DOMPurify + heuristic quality gate are in place. |
| R-05 | `GenerateArticle` job not `ShouldBeUnique` | Low | Atomic claim prevents duplicate LLM calls. Queue clutter only. |
| R-06 | Audit log frontend missing `resource` filter | Low | Backend supports it, frontend only wires `action` filter. Cosmetic. |
| R-07 | No staging environment config | Medium | Can be added post-launch. Same docker-compose works with different env vars. |
| R-08 | Frontend `npm install` not `npm ci` in Dockerfile | Low | Should use `npm ci` for reproducible builds. |

---

## Deferred (Monetization — Not Implementing Now)

- Payment processor integration (Stripe/Paddle)
- Subscription/plan/tier/quota model
- Paywall/content gating
- Email sending infrastructure (SMTP/SES/Postmark)
- Double opt-in + confirmation email
- Invoice/receipt/tax/VAT handling
- DSAR (data export/deletion) flow
- Newsletter unsubscribe token + link in emails
- LLM cost budget alerts

---

## Implementation Log

| Date | Task | Status | Notes |
|------|------|--------|-------|
| 2026-06-21 | Created progress tracker | Done | |
| 2026-06-22 | SEC-01 through SEC-08 | Done | All security fixes |
| 2026-06-22 | AI-01 through AI-06 | Done | All AI safety fixes |
| 2026-06-22 | DB-01 through DB-05 | Done | All database fixes |
| 2026-06-22 | OBS-01, OBS-02, OBS-04 | Done | Audit logging, JSON logging, health check |
| 2026-06-22 | COMPL-01 through COMPL-04 | Done | All compliance fixes |
| 2026-06-22 | INFRA-01 through INFRA-06 | Done | Backup, non-root, CI, tests, no-auto-migrate |
| 2026-06-22 | N+1 eager load fix | Done | ArticleRepository |
| 2026-06-22 | Frontend healthcheck | Done | docker-compose.yml |