# The Neural Journal — SEO Plan (Sep 2026)

> Status snapshot after the 2026-09-10 fix batch (commits `cb87269`). Sources: codebase audit
> (seo-specialist agent), live OpenSEO audit (50 pages), manual SSR checks.

## Shipped (2026-09-10, commit cb87269)

- ✅ **Canonical domain fixed** — `siteUrl` default → `https://news.technioz.com` (article
  canonicals + JSON-LD previously pointed at non-serving `theneuraljournal.com`).
  `NUXT_PUBLIC_SITE_URL` env override still wins (set it if/when migrating domains).
- ✅ robots.txt + feed.xml URLs use `app.frontend_url` (match sitemap base URL).
- ✅ **Story sitemaps** — `/stories` + `/story/{slug}` (30-day window, lastmod from latest
  timeline entry) in `sitemap-stories.xml`; `/stories`, `/about` added to static pages.
- ✅ **Story pages SEO** — full `useSeoMeta` + `LiveBlogPosting` JSON-LD (liveBlogUpdate entries).
- ✅ Single h1 per page (masthead demoted), deduped title suffix, per-category meta
  descriptions, canonicals on every page, `og:url`, img width/height (CLS), search + legal
  pages `noindex,follow`, fonts `display:swap` + preconnect + trimmed weights, Nitro SWR
  route rules, sitemap.xml proxy header fix, stale static robots.txt deleted.

## Open — need Gaurav's action

1. **Connect Search Console** in OpenSEO (app.openseo.so → News project → integrations).
   Until then: zero impressions/clicks data, no Google News performance visibility.
2. **Cloudflare robots.txt override** — live robots.txt is Cloudflare's AI-Crawl-Control
   managed block (ends `# END Cloudflare Managed Content`) with a STALE cached origin copy
   appended (still contains the deleted static file's `Disallow:`, missing our `Sitemap:`
   lines). Fix in Cloudflare → AI Crawl Control: disable the robots.txt takeover, or purge
   `/robots.txt`. AI-bot blocking (GPTBot/ClaudeBot/etc.) looks intentional — keep if so,
   but submit sitemaps directly in Search Console since crawlers won't see our Sitemap refs.
3. **Domain decision** — if `theneuraljournal.com` is a planned future migration, the move
   needs a proper 301 strategy from news.technioz.com; the hardcoded default now points at
   the serving domain.

## Open — next SEO work (ranked)

1. **Story monitor restore** (production issue, blocks fresh timeline content — the strongest
   SEO surface). Diagnose via Coolify backend logs or container restart.
2. **Pagination crawlability** (MEDIUM) — category + story pages are "Load more" only; add
   URL-based pagination (`?page=2`) with rel next/prev, or keep Load-more + crawlable paginated
   route. Internal PageRank flow beyond page 1 is weak.
3. **Organization + WebSite JSON-LD** site-wide (app.vue) — knowledge panel + sitelinks
   searchbox eligibility.
4. **Crawl depth** — stories hub linked from nav; category sections on homepage cover most;
   consider a "latest" HTML archive page for older articles (currently sitemap-only).
5. **Re-run OpenSEO audit** after this batch lands to confirm: multiple-h1 (50→0),
   duplicate-meta (48→~1), title-too-long (19→0), thin-content re-check.
6. **Google News** — verify publisher center eligibility later; the news sitemap + article
   schema are prerequisites and already in place.

## Monitoring cadence

- OpenSEO site audit: weekly (50 pages) after each deploy batch.
- Track: indexed pages, Top Stories appearances for category terms, CWV on article pages.
- GSC data review weekly once connected.

## Related decisions

- Volume/quality: dedup hardening + per-story daily article cap + hourly-discovery stem
  matching (separate batch, 2026-09-10) — quality over volume per Gaurav's direction.
- Dup-article cleanup: `news:dedupe-articles` (--apply) — run once post-deploy, rerun as needed.