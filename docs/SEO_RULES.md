# The Neural Journal — Per-Page SEO Structure Rules

> Derived from the Ahrefs site audit (12 Sep 2026, 19 issue types, 28/100 score) and the
> unified per-URL issue map (`~/Downloads/neural-issues/unified-per-url-issues.csv`).
> These rules govern the STRUCTURE of one page/blog — generation, templates, and QA.
> Internal linking rules live separately (crawlability is a site-level concern).
> Every rule exists because a specific audit issue violated it.

## 1. Title tag (`<title>`)

| Rule | Value |
|---|---|
| Total length (incl. brand suffix) | **≤ 65 chars** (Ahrefs flag threshold; Google truncates ~60) |
| Article headline portion | **≤ 60 chars**, clamped at word boundary with ellipsis |
| Brand suffix on ARTICLES | **None** — article pages set `titleTemplate: '%s'` and render the bare headline (suffix ≈ 20 chars would push every article past 65; verified against the 1,771-page flag) |
| Brand suffix on other pages | `— The Neural Journal` stays for home/category/story/etc. — keep those titles ≤ 45 |
| Uniqueness | Every page must have a **unique** title — slug-collision suffixes (`-2`) are a hard failure |
| Style | Headline-style, no keyword stuffing, no broken auto-questions |

Enforced at generation (`MetaClamp::clampTitle`, 60) — never trust the LLM's own length.

## 2. Meta description

| Rule | Value |
|---|---|
| Length | **120–155 chars** (hard band — Ahrefs flags < 70 as short, > 160 as long) |
| Source | Derived from the article's own content (first meaningful sentence), clamped at word boundary |
| Fallback | If a content-derived description lands < 120 chars → title-based description |
| Uniqueness | **No two pages may share a meta description** — collision → title-based variant (enforced in `news:regenerate-article-meta` and the frontend fallback) |
| Never | Do not populate `meta_keywords` (ignored by Google; previously stuffed with broken auto-questions) |
| Empty | Never render a page without a description — frontend fallback chain: `meta_description` → content excerpt → title |

## 3. Open Graph + Twitter cards (every page)

- `og:title`, `og:description`, `og:type`, `og:url` — always present, `og:url` absolute and equal to the canonical
- `og:image` — **every page must emit one**: per-article image when available, else the site default card
- `og:image:width=1200`, `og:image:height=630`, `og:image:alt` (descriptive)
- `og:site_name`, `og:locale`
- `twitter:card=summary_large_image`, `twitter:site`, `twitter:title/description/image`
- Article images used as og:image: ≥ 320×180, ≥ 20KB, never logos/icons/pixels (blocklist in `NewsArticleImageService`)

## 4. Structured data (JSON-LD)

- **Must render as script CONTENT, not attributes** — unhead v2 uses `innerHTML:`; `children:` renders an attribute and Google sees nothing (this bug hid 100% of schema site-wide)
- Articles: `NewsArticle` (headline, description, datePublished, dateModified, author, mainEntityOfPage) + `BreadcrumbList` + `FAQPage` (only when sources support distinct Q&As)
- Story pages: `LiveBlogPosting` with `liveBlogUpdate` entries
- Every block must `JSON.parse` cleanly in the SSR HTML — verified by rendering, not by code inspection

## 5. Headings

- **Exactly one `<h1>` per page** — the page title; article content must never contain `h1` (sanitizer demotes to `h2`)
- Order: `h1 → h2 → h3`, no level skips
- Article structure: 2–4 `h2` sections (vary by source richness — identical templates across articles are a scaled-content fingerprint)

## 6. Document + localization

- `<html lang="en">` on every page (`app.head.htmlAttrs`)
- Charset + viewport present

## 7. Canonical + URLs

- Absolute self-canonical on every page; pagination pages canonicalize to their own URL **including** `?page=N`
- Canonical host must equal the serving domain (never a non-serving domain)
- Slugs: lowercase, hyphenated, no collision artifacts; no URL duplicates of the same article

## 8. Content body (per blog)

| Rule | Value |
|---|---|
| Word count | ≥ 300 (thin-content threshold), typical 580–750 |
| read_time | **computed** `max(2, round(words/220))` — never hardcoded |
| Quotes | Only verbatim from source materials — mechanical verification strips ungrounded quote sentences |
| Numbers/statistics | Only if present in sources |
| Entities/titles | Only organizations, officials, and titles that appear in sources |
| Structure variation | 2–4 H2 sections; FAQ optional; no forced fixed template |

## 9. Sitemaps

- Every sitemap URL must return **200** — exclude empty/erroring articles at generation
- Each URL appears in **exactly one** sitemap: articles within the 48h news window live only in the news sitemap; older articles in article sitemaps
- Valid XML, correct namespaces (news: namespace for the news sitemap), 1000-URL chunking
- Regenerated every 30 min; lastmod accurate

## 10. Performance (crawl-facing)

- Article/category/story pages: SWR-cached (120–600s) — no fresh SSR per crawl hit
- Slow-page budget: crawler TTFB < ~1s on cached pages
- AI-crawler response: same cache applies; never block-list Googlebot-News

## Pre-GSC validation protocol

1. Render, don't inspect: pull the SSR HTML for each page type and verify (grep + JSON.parse), never trust code alone
2. After fixes: full re-crawl (Ahrefs), export CSVs, rebuild the unified per-URL map, and diff against the previous map before declaring anything resolved