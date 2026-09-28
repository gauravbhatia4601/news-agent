# Spec: /ai hub M2 — live model intelligence data (DB-backed)

## Problem Statement

The `/ai` hub page currently shows model pricing and context-window charts fed from hard-coded fixtures inside the page. The numbers are static, quickly stale, and only cover a dozen hand-typed models. Visitors looking for an AI "information website" get a two-graph page with no way to browse the wider model landscape, and the data behind the charts cannot be trusted or refreshed without a code deploy.

## Solution

Move model intelligence data into the site's own database, synced hourly from the OpenRouter public models feed. The `/ai` page then renders from that data: the two existing charts show a curated top 20 of flagship models, and a new searchable, sortable table below shows every active model with its provider, context window, and input/output price per million tokens. If the sync has never run (empty tables), the data section hides gracefully and the AI news feed remains.

The site never reads live from OpenRouter — the database is the source the page reads from.

## User Stories

1. As a visitor, I want model pricing charts on `/ai` to show real, current prices, so that the numbers I read are trustworthy.
2. As a visitor, I want the charts to show the ~20 flagship models people talk about, so that the visual overview stays readable.
3. As a visitor, I want a full table of every tracked model below the charts, so that I can look up any model, not just the famous ones.
4. As a visitor, I want to search the table by model name, so that I can find one specific model quickly.
5. As a visitor, I want to filter the table by provider, so that I can see only OpenAI's or only Anthropic's models.
6. As a visitor, I want to sort the table by price, context window, or name, so that I can compare models the way I care about.
7. As a visitor, I want prices shown as $ per million tokens, so that the numbers are comparable across models.
8. As a visitor, I want to see each model's context window, so that I know how much text it can handle.
9. As a visitor, I want to see which provider (company) each model belongs to, so that I can group my understanding by lab.
10. As a visitor, I want a "synced at" timestamp on the page, so that I know how fresh the data is.
11. As a visitor, I want the page to still work when model data is unavailable, so that I always get the AI news feed below.
12. As a visitor using a screen reader, I want the table to be real HTML table markup, so that I can navigate the data accessibly.
13. As a search engine crawler, I want the model table server-rendered, so that the data page is indexable content.
14. As the site owner, I want model data stored in my own database, so that the page keeps working even if the upstream feed goes down.
15. As the site owner, I want the catalog refreshed hourly, so that pricing stays current without manual action.
16. As the site owner, I want nothing deleted from the catalog, so that I keep a history trail of models that disappear from the feed.
17. As the site owner, I want provider and model stored in relational tables, so that future features (per-provider pages, comparisons) have real data to build on.
18. As the site owner, I want batch and free variants stored but hidden by default, so that the data is complete without cluttering the page.
19. As the site owner, I want the curated top-20 list maintained in one config place, so that updating the chart selection doesn't touch frontend code.
20. As a developer, I want the sync to upsert (not duplicate) rows, so that hourly runs don't grow the table unboundedly.

## Implementation Decisions

- **Data storage**: two new tables — `ai_model_providers` (slug, name) and `ai_models` (belongs to provider; feed id, name, context length, input price, output price, modality, variant flag, is_active, first/last seen timestamps). Prices are stored in USD per million tokens (converted at sync time from the feed's per-token strings).
- **Sync**: a `ai:sync-models` artisan command upserts the full feed. One row per feed entry; no row is ever deleted. Entries absent from the feed are marked `is_active = false` and keep their last-seen timestamp. Runs hourly on the scheduler at an off-minute. Sync sets a `last_synced_at` marker used by the page footer.
- **Single source**: the API endpoint reads only from the database. There is no live-call path and no cache-fallback-to-live path. (Recorded as ADR 0001.)
- **API contract**: `GET /api/v1/ai/models` (inside the existing v1 throttle group) returns `{ data: { synced_at: string|null, models: AiModelRow[] } }`, where each row carries its provider name and a `featured: boolean` flag derived from the curated top-20 list. The curated list lives in backend config (provider-prefixed id list).
- **Variant rule**: `:batch` and `:free` suffixed ids are stored with a variant marker and excluded from the default API response; the base model row (a sibling without the suffix) carries the standard pricing.
- **Modality**: the feed's `architecture.modality` string (e.g. `text+image->text`) is stored as-is and exposed so the existing charts can distinguish text-only vs multimodal in M3.
- **Degradation**: empty DB → API returns an empty list; the page hides the data section (existing MarketTicker degradation pattern). Fixtures are deleted, not kept as fallback.
- **Frontend consumption**: the composable gains a `getAiModels()` call mirroring `getMarketData()`. The page switches from fixtures to `useAsyncData` with SSR enabled. Chart components keep their existing props interface — the row type gains a provider name field.
- **The table (M2b)**: server-rendered HTML table with client-side search input, provider filter, and sort toggles (price, context, name). Native controls only — no state library, no chart dependency. Accessibility: real `<th scope>` headers, labelled search input.
- **SEO**: the data section stays server-rendered so the table is crawlable; page SEO block (title/description/canonical/JSON-LD) is unchanged from M1 except the description no longer over-promises a "leaderboard".

## Testing Decisions

- Test external behavior only: what the sync command writes, what the endpoint returns, what the scheduler wiring exists. Never test private method shapes or DB query internals.
- The sync command is the primary seam: `Http::fake` the OpenRouter feed with a realistic fixture (base models, `:batch`/`:free` twins, bad/missing pricing rows, a model that later disappears), run `artisan ai:sync-models`, and assert table state (rows created, prices converted ×1e6, variants marked, re-run upserts instead of duplicating, missing models deactivated). Prior art: `DedupeArticlesCommandTest` / `DetectLiveStoriesCommandTest` (`$this->artisan(...)`, `RefreshDatabase`).
- The endpoint is the second seam: seed the DB (or run the sync), `GET /api/v1/ai/models`, assert the JSON shape, the `featured` flag, variant exclusion, and the empty-DB case. Prior art: `HotEndpointTest` / `FeedAndHealthTest`.
- The hourly schedule wiring gets one assertion that the command is scheduled (mirrors how other `news:*` commands are covered).

## Out of Scope

- Hugging Face or any second feed — OpenRouter only (parked for later).
- M3 interactivity (filter chips on charts, output-price toggle, provider colors, hover highlighting).
- Batch/free variant display toggles (data is stored, UI comes later).
- Any "intelligence"/quality score — no free API supplies one.
- Backfilling or re-syncing historical pricing (rows keep last-seen only, no history table).
- Admin UI for the curated list (config edit + deploy is enough at this scale).
- Live-ticker, auto-refresh in browser, or websockets.

## Further Notes

- The hourly sync must not collide with the existing scheduler minutes (discovery :30, live-story detection :10/:40) — pick a free minute.
- The feed is 458 rows today (~374 base models, 63 providers) and grows; the endpoint response must stay one payload (fine at this size) but the table should paginate or cap initial render client-side.
- `CONTEXT.md` now defines the vocabulary: Model, Variant, Provider, Sync — use these terms in code, config, and tests.
- The existing `AiModelRow` type in the scatter chart component is the shape the endpoint should feed; it will need a provider-name field added in M2b.
- The `/ai` page's SEO block currently says "leaderboard" in its title per M1 — M2b should revisit it per `docs/SEO_RULES.md`.