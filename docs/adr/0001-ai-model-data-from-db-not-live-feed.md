# AI model catalog data comes from our database, never live from the feed

The `/ai` hub's model intelligence (charts, table) reads exclusively from our own
`ai_models`/`ai_model_providers` tables. A scheduled `ai:sync-models` command copies the
OpenRouter public feed into those tables every hour; the API endpoint and page never call
OpenRouter live and never fall back to a live call.

Why not just cache the API response: a cache-only design makes the page's data vanish or
go stale-error whenever the feed is down, gives us no control over which rows exist
(curation, deactivation, future sources like Hugging Face), and offers no place to attach
our own flags (featured, variant). Storing relationally (provider FK, variant marker,
is_active, first/last-seen) turns the feed into a durable catalog we own. The cost is one
hourly sync and a small migration — cheap against the resilience gained.

Rejected alternative: second source (Hugging Face) at the same time — deduping two feeds
is real work; the `source` column keeps the door open without doing it now.