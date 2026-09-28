# The Neural Journal

An autonomous AI news engine: discovers topics from news feeds, synthesizes multi-source articles with LLMs, and auto-publishes a public newspaper site.

## Language

### News pipeline

**Topic**:
A clustered news subject discovered from RSS feeds; the unit that gets claimed and turned into one article.
_Avoid_: story (a Story is a live-timeline entity), subject

**Story**:
An ongoing event with a live timeline (war, match, rescue), detected automatically and updated by a monitor.
_Avoid_: topic (a Topic becomes one article; a Story accumulates updates)

### Model Intelligence (/ai hub)

**Model**:
One LLM listing as it comes from a provider feed — the canonical unit stored in our database.
_Avoid_: AI model, LLM (use only in prose, not as a data term)

**Variant**:
A twin listing of a Model with different pricing terms — `:batch` (bulk discount) or `:free` (promo). Stored, not shown by default.
_Avoid_: twin, clone, alias

**Provider**:
The company or lab a Model belongs to — the first segment of its feed id (openai, anthropic, google).
_Avoid_: vendor, source (a Source is a news article citation)

**Sync**:
The scheduled refresh of the model catalog from a provider feed into our database. The site reads only from the database, never live from the feed.
_Avoid_: fetch, crawl, import