# The Neural Journal

AI-powered news engine that discovers, synthesizes, and publishes news articles automatically. Built with Laravel 12 + Nuxt 4.

## Overview

The Neural Journal continuously discovers news topics from Google News RSS and Brave Search, synthesizes multi-source articles using LLMs (Ollama Cloud / OpenRouter), and auto-publishes them with heuristic quality gates. It covers India (all states/UTs) and global regions, with a dedicated AI deep-dive vertical producing 1200-2000 word analytical articles.

## Architecture

| Component | Stack |
|-----------|-------|
| Backend API | Laravel 12, PHP 8.4 |
| Frontend | Nuxt 4 (SSR), Vue 3, Tailwind CSS |
| Database | PostgreSQL |
| Cache / Queue / Sessions | Redis |
| AI Providers | Ollama Cloud, OpenRouter (Laravel AI SDK) |
| Deployment | Docker Compose + Coolify |

## Pipeline

1. **Discovery** — hourly `news:discover` scans Google News RSS + Brave Search across 20+ Indian states and 5 global regions, dedupes via fuzzy signature matching
2. **Generation** — `GenerateArticle` queue jobs synthesize articles from 2+ corroborating sources via LLM agents (standard 500-800 words, AI deep-dive 1200-2000 words)
3. **Quality Gate** — automated checks: word count, section count, source diversity, active voice ratio, SEO keyword coverage
4. **Publishing** — auto-publish with source image extraction (og:image scraping), sitemap regeneration, and ranking recompute

## Features

- **Public site**: home feed, trending, category pages, search, article pages with JSON-LD (NewsArticle/BreadcrumbList/FAQPage), RSS feed, Google News sitemaps, market ticker (8 global indices)
- **Admin panel**: dashboard with real-time charts, article/topic/category management, queue monitor, sitemap manager, newsletter subscribers, AI invocation tracking (token usage + cost), audit logs
- **Newsletter**: GDPR-compliant capture with consent records (IP, UA, consent text)
- **Monetization-ready**: cookie consent banner, AdSense-ready ad slots, ads.txt

## Security

- Rate limiting on all endpoints (login, public API, newsletter, admin actions)
- Sanctum token expiration (24h)
- DOMPurify XSS sanitization on article content
- Prompt-injection guards on scraped source content
- Atomic topic claiming (prevents duplicate generation race conditions)
- Audit logging on all admin actions
- Non-root Docker containers

## Getting Started

```bash
# Backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# Frontend
cd frontend
npm install
npm run dev
```

## Scheduled Tasks

| Task | Frequency |
|------|-----------|
| `news:discover --queue` (India) | Hourly |
| `news:discover --queue --scope=global` | Hourly at :30 |
| `news:sitemap-generate` | Every 30 min |
| `news:recompute-rankings` | Every 15 min |
| `news:backup-db` | Daily 2:00 AM |
| Queue log / token / subscriber pruning | Daily |

## Testing

```bash
php artisan test
```

14 feature tests covering auth and newsletter flows. CI pipeline (GitHub Actions) runs PHP lint, tests, and Docker builds.

## Environment Variables

Key configuration in `.env.example`:

- `NEWS_GENERATION_PROVIDER` / `NEWS_GENERATION_MODEL` — LLM provider and model
- `NEWS_GENERATION_FALLBACK_PROVIDER` / `NEWS_GENERATION_FALLBACK_MODEL` — failover
- `NEWS_DISCOVERY_LIMIT` / `NEWS_DISCOVERY_FRESH_HOURS` — discovery tuning
- `NEWS_SOURCE_IMAGES_ENABLED` — source image extraction
- `ADSENSE_CLIENT` / `ADSENSE_SLOT` — AdSense (empty = ads hidden)
- `SANCTUM_TOKEN_EXPIRATION` — admin token lifetime in minutes
- `RUN_MIGRATIONS` — auto-migrate on container boot

## License

Proprietary. All rights reserved.
