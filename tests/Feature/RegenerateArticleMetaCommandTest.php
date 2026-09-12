<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegenerateArticleMetaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_write_to_db(): void
    {
        $article = NewsArticle::factory()->create([
            'status' => 'published',
            'title' => 'Short Title',
            'meta_title' => 'Old Meta Title That Is Different',
            'content' => '<p>This is a sufficiently long article content with multiple sentences. It has enough text to generate a proper meta description that exceeds the minimum threshold.</p>',
        ]);

        $this->artisan('news:regenerate-article-meta')
            ->assertSuccessful();

        $article->refresh();
        // Dry-run: no change
        $this->assertSame('Old Meta Title That Is Different', $article->meta_title);
    }

    public function test_apply_writes_clamped_meta_title(): void
    {
        $longTitle = 'India GDP Growth Surges to 6.5 Percent in Q3 2024 Beating Economist Estimates and Market Predictions Everywhere';

        $article = NewsArticle::factory()->create([
            'status' => 'published',
            'title' => $longTitle,
            'meta_title' => 'Short Old Title',
            'content' => '<p>Sufficient article content with enough words to produce a meaningful meta description for testing purposes here.</p>',
        ]);

        $this->artisan('news:regenerate-article-meta --apply')
            ->assertSuccessful();

        $article->refresh();
        $this->assertNotSame('Short Old Title', $article->meta_title);
        $this->assertLessThanOrEqual(61, mb_strlen($article->meta_title));
        // Display title must NOT change
        $this->assertSame($longTitle, $article->title);
    }

    public function test_apply_writes_meta_description_from_content(): void
    {
        $article = NewsArticle::factory()->create([
            'status' => 'published',
            'title' => 'Test Article Title',
            'meta_description' => 'Short old desc',
            'content' => '<p>This is the first sentence of a meaningful article. The second sentence adds more context. The third is here too.</p>',
        ]);

        $this->artisan('news:regenerate-article-meta --apply')
            ->assertSuccessful();

        $article->refresh();
        $this->assertNotSame('Short old desc', $article->meta_description);
        $this->assertGreaterThanOrEqual(20, mb_strlen($article->meta_description));
        $this->assertLessThanOrEqual(155, mb_strlen($article->meta_description));
    }

    public function test_collision_resolution_uses_title_fallback(): void
    {
        // Two articles with identical content → same first sentence → collision
        $content = '<p>Identical first sentence for both articles about the same topic. More text follows here for length purposes.</p>';

        $article1 = NewsArticle::factory()->create([
            'status' => 'published',
            'title' => 'Article One Unique Title',
            'content' => $content,
        ]);

        $article2 = NewsArticle::factory()->create([
            'status' => 'published',
            'title' => 'Article Two Different Title',
            'content' => $content,
        ]);

        $this->artisan('news:regenerate-article-meta --apply')
            ->assertSuccessful();

        $article1->refresh();
        $article2->refresh();

        // Descriptions must differ (collision resolved)
        $this->assertNotSame($article1->meta_description, $article2->meta_description);
    }

    public function test_does_not_process_draft_articles(): void
    {
        $draft = NewsArticle::factory()->create([
            'status' => 'draft',
            'title' => 'Draft Title',
            'meta_title' => 'Old Draft Meta',
        ]);

        $this->artisan('news:regenerate-article-meta --apply')
            ->assertSuccessful();

        $draft->refresh();
        $this->assertSame('Old Draft Meta', $draft->meta_title);
    }
}
