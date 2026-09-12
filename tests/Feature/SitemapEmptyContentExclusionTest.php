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
        $goodArticle = NewsArticle::factory()->create([
            'status' => 'published',
            'content' => '<p>Real article content with substance.</p>',
        ]);

        $emptyContent = NewsArticle::factory()->create([
            'status' => 'published',
            'content' => '',
        ]);

        $whitespaceContent = NewsArticle::factory()->create([
            'status' => 'published',
            'content' => '   ',
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
