<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published article 78 days old is excluded from /hot; a fresh one is present.
     */
    public function test_hot_excludes_articles_outside_window(): void
    {
        $old = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subDays(78),
            'hot_score' => 1000,
        ]);

        $fresh = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now(),
            'hot_score' => 0,
        ]);

        $response = $this->getJson('/api/v1/articles/hot');

        $response->assertStatus(200);

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertNotContains($old->slug, $slugs, '78-day-old article must not appear in hot despite high hot_score.');
        $this->assertContains($fresh->slug, $slugs, 'Fresh in-window article must appear.');
    }

    /**
     * Two in-window articles with equal hot_score: newer published_at ranks first.
     */
    public function test_hot_recency_tiebreaker(): void
    {
        $newer = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHour(),
            'hot_score' => 0,
        ]);

        $older = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHours(10),
            'hot_score' => 0,
        ]);

        $response = $this->getJson('/api/v1/articles/hot');

        $response->assertStatus(200);

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertSame($newer->slug, $slugs[0], 'Newer published_at must rank first when hot_score ties.');
        $this->assertSame($older->slug, $slugs[1], 'Older in-window article comes second.');
    }

    /**
     * Category-filtered hot still respects the recency window.
     */
    public function test_hot_with_category_respects_window(): void
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        // topic_id is unique on news_articles, so each article gets its own topic
        // sharing the same category_id.
        $oldTopic = NewsTopic::factory()->create(['category_id' => $category->id]);
        $old = NewsArticle::factory()->create([
            'topic_id' => $oldTopic->id,
            'status' => 'published',
            'published_at' => now()->subDays(78),
            'hot_score' => 1000,
        ]);

        $freshTopic = NewsTopic::factory()->create(['category_id' => $category->id]);
        $fresh = NewsArticle::factory()->create([
            'topic_id' => $freshTopic->id,
            'status' => 'published',
            'published_at' => now(),
            'hot_score' => 0,
        ]);

        $response = $this->getJson('/api/v1/articles/hot?category=technology');

        $response->assertStatus(200);

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertNotContains($old->slug, $slugs, 'Out-of-window article excluded under category filter.');
        $this->assertContains($fresh->slug, $slugs, 'In-window article present under category filter.');
    }

    /**
     * With no published article in the 24h hero window, featured falls back to latest published.
     */
    public function test_featured_falls_back_to_latest(): void
    {
        // Both articles outside the 24h hero window → momentum query returns null → fallback.
        $latest = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHours(30),
        ]);

        NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subDays(3),
        ]);

        $response = $this->getJson('/api/v1/articles/featured');

        $response->assertStatus(200);

        $this->assertSame($latest->slug, $response->json('data.slug'), 'Featured must fall back to latest-published when no article is in the hero window.');
    }

    /**
     * published_at in API response equals the real column value (not created_at).
     */
    public function test_published_at_exposes_real_column(): void
    {
        $publishedAt = now()->subDays(2)->startOfSecond();

        $article = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => $publishedAt,
        ]);

        $response = $this->getJson('/api/v1/articles/'.$article->slug);

        $response->assertStatus(200);

        $this->assertSame(
            $publishedAt->toIso8601String(),
            $response->json('data.published_at'),
            'API must expose the real published_at column, not created_at.'
        );
    }

    /**
     * Category index paginates — page 2 returns meta.current_page === 2.
     */
    public function test_category_index_paginates(): void
    {
        $category = Category::create([
            'name' => 'Business',
            'slug' => 'business',
        ]);

        // topic_id is unique on news_articles — one topic per article, same category_id.
        for ($i = 0; $i < 5; $i++) {
            $topic = NewsTopic::factory()->create(['category_id' => $category->id]);

            NewsArticle::factory()->create([
                'topic_id' => $topic->id,
                'status' => 'published',
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->getJson('/api/v1/articles?category=business&per_page=3&page=2');

        $response->assertStatus(200);

        $this->assertSame(2, $response->json('meta.current_page'), 'Paginator meta.current_page must be 2 on page 2.');
        $this->assertCount(2, $response->json('data'), 'Page 2 of a 5-row per_page=3 set has 2 rows.');
    }
}
