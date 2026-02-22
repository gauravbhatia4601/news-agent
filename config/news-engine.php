<?php

return [

    /*
    |--------------------------------------------------------------------------
    | News Categories
    |--------------------------------------------------------------------------
    |
    | Categories discovered by the RSS source adapters.
    |
    */
    'categories' => array_filter(array_map('trim', explode(',', env('NEWS_DISCOVERY_CATEGORIES', 'technology,sports,politics,entertainment,social')))) ?: [
        'technology',
        'sports',
        'politics',
        'entertainment',
        'social',
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery Defaults
    |--------------------------------------------------------------------------
    */
    'discovery' => [
        'default_limit' => (int) env('NEWS_DISCOVERY_LIMIT', 5),
        'default_fresh_hours' => (int) env('NEWS_DISCOVERY_FRESH_HOURS', 12),
        'default_sources_per_topic' => (int) env('NEWS_DISCOVERY_SOURCES_PER_TOPIC', 3),
        'seen_cache_key' => env('NEWS_DISCOVERY_SEEN_CACHE_KEY', 'news-engine:rss:seen-signatures'),
        'seen_cache_ttl_seconds' => (int) env('NEWS_DISCOVERY_SEEN_CACHE_TTL_SECONDS', 172800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Article Generation
    |--------------------------------------------------------------------------
    */
    'generation' => [
        'enabled' => (bool) env('NEWS_GENERATION_ENABLED', true),
        'provider' => env('NEWS_GENERATION_PROVIDER', env('AI_DEFAULT_PROVIDER', 'openrouter')),
        'model' => env('NEWS_GENERATION_MODEL', 'perplexity/sonar'),
        'timeout' => (int) env('NEWS_GENERATION_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Article Images
    |--------------------------------------------------------------------------
    |
    | Reliability-first default: disabled.
    | Enable source and/or AI images explicitly via env once quality is acceptable.
    |
    */
    'images' => [
        'enabled' => (bool) env('NEWS_IMAGES_ENABLED', false),

        'source' => [
            'enabled' => (bool) env('NEWS_SOURCE_IMAGES_ENABLED', false),
            'max_attempts' => (int) env('NEWS_SOURCE_IMAGES_MAX_ATTEMPTS', 3),
            'html_timeout' => (int) env('NEWS_SOURCE_IMAGES_HTML_TIMEOUT', 10),
            'image_timeout' => (int) env('NEWS_SOURCE_IMAGES_DOWNLOAD_TIMEOUT', 20),
        ],

        'ai' => [
            'enabled' => (bool) env('NEWS_AI_IMAGE_ENABLED', false),
            'provider' => env('NEWS_AI_IMAGE_PROVIDER', 'pollinations'),
            'style_prompt' => env(
                'NEWS_AI_IMAGE_STYLE_PROMPT',
                'Minimalist editorial illustration, modern flat style, clean composition, soft neutral palette, no text, no logos, no watermarks.'
            ),
            'pollinations' => [
                'base_url' => env('NEWS_AI_IMAGE_BASE_URL', 'https://image.pollinations.ai'),
                'model' => env('NEWS_AI_IMAGE_MODEL', 'flux'),
                'width' => (int) env('NEWS_AI_IMAGE_WIDTH', 1536),
                'height' => (int) env('NEWS_AI_IMAGE_HEIGHT', 864),
                'timeout' => (int) env('NEWS_AI_IMAGE_TIMEOUT', 35),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Registry
    |--------------------------------------------------------------------------
    |
    | Add future sources in "enabled" and define options under matching keys.
    |
    */
    'sources' => [
        'enabled' => array_filter(array_map('trim', explode(',', env('NEWS_DISCOVERY_SOURCES', 'google_rss')))),

        'google_rss' => [
            'base_feed_url' => env('GOOGLE_NEWS_RSS_URL', 'https://news.google.com/rss/search'),
            'hl' => env('GOOGLE_NEWS_RSS_HL', 'en-US'),
            'gl' => env('GOOGLE_NEWS_RSS_GL', 'US'),
            'ceid' => env('GOOGLE_NEWS_RSS_CEID', 'US:en'),
            'timeout' => (int) env('GOOGLE_NEWS_RSS_TIMEOUT', 20),
            'queries' => [
                'technology' => '(technology OR ai OR software OR cybersecurity OR startup) -wikipedia -britannica',
                'sports' => '(sports OR football OR basketball OR tennis OR cricket) -puzzle -connections',
                'politics' => '(politics OR government OR congress OR election OR parliament)',
                'entertainment' => '(entertainment OR movies OR music OR streaming OR celebrity)',
                'social' => '("social media" OR society OR public policy OR education OR health policy)',
            ],
        ],
    ],
];
