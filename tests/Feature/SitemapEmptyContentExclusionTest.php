<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the sitemap excludes published articles with empty/whitespace-only
 * content — these produce 5XX on the frontend and should not be crawled.
 */
class SitemapEmptyContentExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_excludes_articles_with_empty_content(): void
    {
        // Each article needs its own topic (topic_id has a unique constraint)
        // Articles must be older than 48h to appear in the article sitemap
        // (recent ones go to the news sitemap only).
        $goodArticle = NewsArticle::factory()->create([
            'status' => 'published',
            'content' => '<p>Real article content with substance.</p>',
            'created_at' => now()->subDays(3),
        ]);

        $emptyContent = NewsArticle::factory()->create([
            'status' => 'published',
            'content' => '',
            'created_at' => now()->subDays(3),
        ]);

        $whitespaceContent = NewsArticle::factory()->create([
            'status' => 'published',
            'content' => '   ',
            'created_at' => now()->subDays(3),
        ]);

        $service = app(SitemapService::class);
        $service->generate();

        $articleSitemap = file_get_contents(public_path('sitemaps/sitemap-articles-1.xml'));

        // Good article must be in the sitemap
        $this->assertStringContainsString("/article/{$goodArticle->slug}", $articleSitemap);

        // Empty/whitespace articles must be excluded
        $this->assertStringNotContainsString("/article/{$emptyContent->slug}", $articleSitemap);
        $this->assertStringNotContainsString("/article/{$whitespaceContent->slug}", $articleSitemap);
    }

    public function test_article_endpoint_returns_404_for_empty_content(): void
    {
        $emptyArticle = NewsArticle::factory()->create([
            'status' => 'published',
            'content' => '   ',
        ]);

        $response = $this->getJson("/api/v1/articles/{$emptyArticle->slug}");

        $response->assertStatus(404);
    }

    public function test_article_sitemap_excludes_recent_48h_articles(): void
    {
        // Old article (> 48h) — must be in article sitemap
        $oldArticle = NewsArticle::factory()->create([
            'status' => 'published',
            'content' => '<p>Real article content with substance.</p>',
            'created_at' => now()->subDays(3),
        ]);

        // Recent article (< 48h) — must be in news sitemap only, NOT article sitemap
        $recentArticle = NewsArticle::factory()->create([
            'status' => 'published',
            'content' => '<p>Breaking news content for the news sitemap.</p>',
            'created_at' => now()->subHours(12),
        ]);

        $service = app(SitemapService::class);
        $service->generate();

        $articleSitemap = file_get_contents(public_path('sitemaps/sitemap-articles-1.xml'));
        $newsSitemap = file_get_contents(public_path('sitemaps/sitemap-news.xml'));

        // Old article in article sitemap
        $this->assertStringContainsString("/article/{$oldArticle->slug}", $articleSitemap);
        // Recent article NOT in article sitemap (covered by news sitemap)
        $this->assertStringNotContainsString("/article/{$recentArticle->slug}", $articleSitemap);
        // Recent article IS in news sitemap
        $this->assertStringContainsString("/article/{$recentArticle->slug}", $newsSitemap);
    }

    protected function tearDown(): void
    {
        // Clean up generated sitemap files
        $dir = public_path('sitemaps');
        if (is_dir($dir)) {
            foreach (glob($dir.'/sitemap*.xml') ?: [] as $file) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }
}
