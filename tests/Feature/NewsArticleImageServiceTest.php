<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\News\Services\NewsArticleImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsArticleImageServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $ogImage = 'https://cdn.reuters.com/article-image-2024-09.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Source page returns og:image → downloaded, stored, image_origin 'source'.
     */
    public function test_source_page_with_og_image_stores_source_image(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);
        config()->set('news-engine.images.ai.enabled', true);

        Http::fake([
            'https://reuters.com/article-123' => Http::response(
                '<html><head><meta property="og:image" content="'.$this->ogImage.'"></head><body>x</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            $this->ogImage => Http::response(
                str_repeat('x', 30_000),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
            // No pollinations call expected, but fake it defensively.
            'image.pollinations.ai/*' => Http::response(str_repeat('y', 30_000), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $service = $this->app->make(NewsArticleImageService::class);
        $result = $service->resolveImageForTopic([
            'topic_name' => 'Parliament passes bill',
            'category' => 'national',
            'sources' => [['source_url' => 'https://reuters.com/article-123']],
        ], 'Parliament passes key bill today');

        $this->assertSame('source', $result['image_origin']);
        $this->assertNotNull($result['image_url']);
        $this->assertStringStartsWith('/storage/news-images/', $result['image_url']);
    }

    /**
     * Source page has no og:image → AI fallback via pollinations → image_origin 'ai'.
     */
    public function test_no_source_image_falls_back_to_ai_generation(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);
        config()->set('news-engine.images.ai.enabled', true);

        Http::fake([
            'https://reuters.com/article-gdp' => Http::response(
                '<html><head><title>No image here</title></head><body>story</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'image.pollinations.ai/*' => Http::response(
                str_repeat('z', 30_000),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $service = $this->app->make(NewsArticleImageService::class);
        $result = $service->resolveImageForTopic([
            'topic_name' => 'India GDP growth',
            'category' => 'business-economy',
            'sources' => [['source_url' => 'https://reuters.com/article-gdp']],
        ], 'India GDP grows 7.8 percent in Q1');

        $this->assertSame('ai', $result['image_origin']);
        $this->assertNotNull($result['image_url']);
        $this->assertStringStartsWith('/storage/news-images/', $result['image_url']);
    }

    /**
     * AI fallback disabled → no image, no pollinations HTTP call.
     */
    public function test_ai_disabled_returns_null_without_calling_pollinations(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);
        config()->set('news-engine.images.ai.enabled', false);

        $pollinationsCalled = false;

        Http::fake([
            'https://reuters.com/article-rbi' => Http::response(
                '<html><head><title>No image</title></head><body>story</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'image.pollinations.ai/*' => function () use (&$pollinationsCalled) {
                $pollinationsCalled = true;

                return Http::response(str_repeat('z', 30_000), 200, ['Content-Type' => 'image/jpeg']);
            },
        ]);

        $service = $this->app->make(NewsArticleImageService::class);
        $result = $service->resolveImageForTopic([
            'topic_name' => 'RBI policy',
            'category' => 'markets-finance',
            'sources' => [['source_url' => 'https://reuters.com/article-rbi']],
        ], 'RBI holds rates steady');

        $this->assertNull($result['image_origin']);
        $this->assertNull($result['image_url']);
        $this->assertFalse($pollinationsCalled);
    }

    /**
     * Google News redirect URLs are decoded before scraping, so the real
     * publisher page is fetched (not the Google redirect).
     */
    public function test_google_news_redirect_is_decoded_before_scraping(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);
        config()->set('news-engine.images.ai.enabled', true);

        $realUrl = 'https://www.thehindu.com/news/national/story-999/article.ece';
        $bytes = chr(0x08).chr(strlen($realUrl)).$realUrl;
        $b64 = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        $googleRedirect = 'https://news.google.com/rss/articles/CBMi'.$b64;

        Http::fake([
            // The decoded URL should be the one hit, not news.google.com.
            $realUrl => Http::response(
                '<html><head><meta property="og:image" content="'.$this->ogImage.'"></head></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            $this->ogImage => Http::response(str_repeat('x', 30_000), 200, ['Content-Type' => 'image/jpeg']),
            'news.google.com/*' => Http::response('redirect page', 200, ['Content-Type' => 'text/html']),
            'image.pollinations.ai/*' => Http::response(str_repeat('y', 30_000), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $service = $this->app->make(NewsArticleImageService::class);
        $result = $service->resolveImageForTopic([
            'topic_name' => 'Topic',
            'category' => 'national',
            'sources' => [['source_url' => $googleRedirect]],
        ], 'Headline');

        $this->assertSame('source', $result['image_origin']);
        $this->assertNotNull($result['image_url']);
    }

    /**
     * Direct-URL sources (GDELT) are tried before Google News redirects.
     * Here the GDELT source succeeds and the Google redirect is never fetched.
     */
    public function test_direct_sources_are_tried_before_google_redirects(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);
        config()->set('news-engine.images.ai.enabled', true);

        $googleRedirect = 'https://news.google.com/rss/articles/CBMi'.str_repeat('A', 20);
        $directUrl = 'https://gdelt-project.org/some-article';

        Http::fake([
            $directUrl => Http::response(
                '<html><head><meta property="og:image" content="'.$this->ogImage.'"></head></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            $this->ogImage => Http::response(str_repeat('x', 30_000), 200, ['Content-Type' => 'image/jpeg']),
            'news.google.com/*' => Http::response('should not be hit', 200, ['Content-Type' => 'text/html']),
        ]);

        $service = $this->app->make(NewsArticleImageService::class);
        $result = $service->resolveImageForTopic([
            'topic_name' => 'Topic',
            'category' => 'national',
            // Redirect listed first — direct source must still win.
            'sources' => [
                ['source_url' => $googleRedirect],
                ['source_url' => $directUrl],
            ],
        ], 'Headline');

        $this->assertSame('source', $result['image_origin']);
        $this->assertNotNull($result['image_url']);
    }

    /**
     * isLikelyArticleImageUrl still rejects favicon/logo/icon fragments.
     */
    public function test_blocked_image_fragments_are_rejected(): void
    {
        $reflection = new \ReflectionMethod(NewsArticleImageService::class, 'isLikelyArticleImageUrl');
        $service = $this->app->make(NewsArticleImageService::class);

        $this->assertFalse($reflection->invoke($service, 'https://example.com/favicon.ico'));
        $this->assertFalse($reflection->invoke($service, 'https://example.com/assets/logo.png'));
        $this->assertFalse($reflection->invoke($service, 'https://gstatic.com/sprite.png'));
        $this->assertTrue($reflection->invoke($service, 'https://cdn.reuters.com/article-photo-2024.jpg'));
    }

    /**
     * Source scraping fails → Brave Images API returns a usable image →
     * downloaded, stored, image_origin 'brave', image_url non-null.
     */
    public function test_brave_images_fallback_stores_image_when_sources_fail(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);
        config()->set('news-engine.images.brave.enabled', true);
        config()->set('news-engine.images.ai.enabled', true);
        config()->set('news-engine.sources.brave_search.api_key', 'test-brave-key');
        config()->set('news-engine.sources.brave_search.base_url', 'https://api.search.brave.com/res/v1');

        $braveImageUrl = 'https://cdn.brave.example.com/india-parliament-photo-2024.jpg';

        Http::fake([
            'https://reuters.com/article-brave' => Http::response(
                '<html><head><title>No image</title></head><body>story</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'api.search.brave.com/res/v1/images/search*' => Http::response([
                'image_results' => [
                    [
                        'url' => $braveImageUrl,
                        'thumbnail' => ['src' => 'https://cdn.brave.example.com/thumb.jpg'],
                        'properties' => ['url' => 'https://example.com/article'],
                        'title' => 'India parliament photo',
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
            $braveImageUrl => Http::response(
                str_repeat('x', 30_000),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
            'image.pollinations.ai/*' => Http::response(str_repeat('y', 30_000), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $service = $this->app->make(NewsArticleImageService::class);
        $result = $service->resolveImageForTopic([
            'topic_name' => 'Parliament passes bill',
            'category' => 'national',
            'sources' => [['source_url' => 'https://reuters.com/article-brave']],
        ], 'Parliament passes key bill today');

        $this->assertSame('brave', $result['image_origin']);
        $this->assertNotNull($result['image_url']);
        $this->assertStringStartsWith('/storage/news-images/', $result['image_url']);
    }

    /**
     * Brave returns empty/garbage → null, and with AI disabled → null image.
     */
    public function test_brave_images_empty_results_returns_null_and_skips_ai_when_disabled(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);
        config()->set('news-engine.images.brave.enabled', true);
        config()->set('news-engine.images.ai.enabled', false);
        config()->set('news-engine.sources.brave_search.api_key', 'test-brave-key');

        Http::fake([
            'https://reuters.com/article-empty' => Http::response(
                '<html><head><title>No image</title></head><body>story</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'api.search.brave.com/res/v1/images/search*' => Http::response(
                ['image_results' => []],
                200,
                ['Content-Type' => 'application/json']
            ),
        ]);

        $service = $this->app->make(NewsArticleImageService::class);
        $result = $service->resolveImageForTopic([
            'topic_name' => 'RBI policy',
            'category' => 'markets-finance',
            'sources' => [['source_url' => 'https://reuters.com/article-empty']],
        ], 'RBI holds rates steady');

        $this->assertNull($result['image_origin']);
        $this->assertNull($result['image_url']);
    }

    /**
     * Second resolve for the same query does not re-hit the Brave API
     * (cached outcome — path or null — reused).
     */
    public function test_brave_images_result_is_cached_per_query(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);
        config()->set('news-engine.images.brave.enabled', true);
        config()->set('news-engine.images.ai.enabled', false);
        config()->set('news-engine.sources.brave_search.api_key', 'test-brave-key');

        $braveCalls = 0;
        $braveImageUrl = 'https://cdn.brave.example.com/cached-photo-2024.jpg';

        Http::fake([
            'https://reuters.com/article-cached' => Http::response(
                '<html><head><title>No image</title></head><body>story</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'api.search.brave.com/res/v1/images/search*' => function () use (&$braveCalls, $braveImageUrl) {
                $braveCalls++;

                return Http::response([
                    'image_results' => [
                        ['url' => $braveImageUrl, 'title' => 'cached photo'],
                    ],
                ], 200, ['Content-Type' => 'application/json']);
            },
            $braveImageUrl => Http::response(
                str_repeat('x', 30_000),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $service = $this->app->make(NewsArticleImageService::class);

        $first = $service->resolveImageForTopic([
            'topic_name' => 'Cache topic',
            'category' => 'national',
            'sources' => [['source_url' => 'https://reuters.com/article-cached']],
        ], 'Cache topic headline');

        $second = $service->resolveImageForTopic([
            'topic_name' => 'Cache topic',
            'category' => 'national',
            'sources' => [['source_url' => 'https://reuters.com/article-cached']],
        ], 'Cache topic headline');

        $this->assertSame('brave', $first['image_origin']);
        $this->assertSame('brave', $second['image_origin']);
        $this->assertSame($first['image_url'], $second['image_url']);
        $this->assertSame(1, $braveCalls, 'Brave Images API must only be hit once for a cached query');
    }

    /**
     * A Brave result whose image URL is in the blocklist (logo.png) is skipped,
     * and the next result is used instead.
     */
    public function test_brave_images_skips_blocklisted_url_and_uses_next_result(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);
        config()->set('news-engine.images.brave.enabled', true);
        config()->set('news-engine.images.ai.enabled', false);
        config()->set('news-engine.sources.brave_search.api_key', 'test-brave-key');

        $goodImageUrl = 'https://cdn.brave.example.com/real-photo-2024.jpg';

        Http::fake([
            'https://reuters.com/article-skip' => Http::response(
                '<html><head><title>No image</title></head><body>story</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'api.search.brave.com/res/v1/images/search*' => Http::response([
                'image_results' => [
                    [
                        // Blocklisted (contains "logo.") — must be skipped.
                        'url' => 'https://cdn.brave.example.com/logo.png',
                        'title' => 'site logo',
                    ],
                    [
                        'url' => $goodImageUrl,
                        'title' => 'real photo',
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
            $goodImageUrl => Http::response(
                str_repeat('x', 30_000),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $service = $this->app->make(NewsArticleImageService::class);
        $result = $service->resolveImageForTopic([
            'topic_name' => 'Skip topic',
            'category' => 'national',
            'sources' => [['source_url' => 'https://reuters.com/article-skip']],
        ], 'Skip topic headline');

        $this->assertSame('brave', $result['image_origin']);
        $this->assertNotNull($result['image_url']);
        $this->assertStringStartsWith('/storage/news-images/', $result['image_url']);
    }
}
