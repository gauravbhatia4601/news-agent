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
     * Source page has no og:image → no image (Brave/AI fallbacks removed:
     * decode+scrape is the only path — articles ship unillustrated).
     */
    public function test_no_source_image_returns_null(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);

        Http::fake([
            'https://reuters.com/article-gdp' => Http::response(
                '<html><head><title>No image here</title></head><body>story</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $service = $this->app->make(NewsArticleImageService::class);
        $result = $service->resolveImageForTopic([
            'topic_name' => 'India GDP growth',
            'category' => 'business-economy',
            'sources' => [['source_url' => 'https://reuters.com/article-gdp']],
        ], 'India GDP grows 7.8 percent in Q1');

        $this->assertNull($result['image_origin']);
        $this->assertNull($result['image_url']);
        $this->assertNull($result['thumbnail_url']);
    }

    /**
     * A no-image page produces null — and never hits any third-party image
     * API (Brave/AI removed 2026-09-27; decode+scrape is the only path).
     */
    public function test_no_image_sources_never_call_third_party_apis(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);

        $thirdPartyCalled = false;

        Http::fake([
            'https://reuters.com/article-rbi' => Http::response(
                '<html><head><title>No image</title></head><body>story</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'image.pollinations.ai/*' => function () use (&$thirdPartyCalled) {
                $thirdPartyCalled = true;

                return Http::response(str_repeat('z', 30_000), 200, ['Content-Type' => 'image/jpeg']);
            },
            'api.search.brave.com/*' => function () use (&$thirdPartyCalled) {
                $thirdPartyCalled = true;

                return Http::response(['image_results' => []], 200, ['Content-Type' => 'application/json']);
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
        $this->assertFalse($thirdPartyCalled);
    }

    /**
     * Google News redirect URLs are decoded before scraping, so the real
     * publisher page is fetched (not the Google redirect).
     */
    public function test_google_news_redirect_is_decoded_before_scraping(): void
    {
        config()->set('news-engine.images.enabled', true);
        config()->set('news-engine.images.source.enabled', true);

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
}
