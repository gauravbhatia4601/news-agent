<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Services\HomeFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The /hot, /featured and /headlines endpoints were replaced by the batched
 * scheduler-warmed /api/v1/home feed (HomeFeedService). These tests keep the
 * original window/tiebreaker/fallback guarantees, now asserted against the
 * batched payload.
 */
class HotEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must exercise build(), not a stale cache.
        Cache::forget(HomeFeedService::CACHE_KEY);
    }

    /**
     * A published article 78 days old is excluded from home.hot; a fresh one is present.
     */
    public function test_hot_excludes_articles_outside_window(): void
    {
        NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subDays(78),
            'hot_score' => 1000,
        ]);

        $fresh = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now(),
            'hot_score' => 0,
        ]);

        $feed = app(HomeFeedService::class)->get();

        $slugs = collect($feed['hot'])->pluck('slug')->all();

        $this->assertNotContains('78-day-old', $slugs, '78-day-old article must not appear in hot despite high hot_score.');
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

        NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHours(10),
            'hot_score' => 0,
        ]);

        $feed = app(HomeFeedService::class)->get();

        $slugs = collect($feed['hot'])->pluck('slug')->all();

        $this->assertSame($newer->slug, $slugs[0], 'Newer published_at must rank first when hot_score ties.');
    }

    /**
     * No published article in the 24h hero window: featured falls back to latest published.
     */
    public function test_featured_falls_back_to_latest(): void
    {
        $latest = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHours(30),
        ]);

        NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subDays(3),
        ]);

        $feed = app(HomeFeedService::class)->get();

        $this->assertSame($latest->slug, $feed['featured']['slug'], 'Featured must fall back to latest-published when no article is in the hero window.');
    }

    /**
     * The batched endpoint returns the full payload shape the homepage consumes.
     */
    public function test_home_endpoint_returns_batched_payload(): void
    {
        $article = NewsArticle::factory()->create(['status' => 'published', 'published_at' => now()]);

        $response = $this->getJson('/api/v1/home');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'featured',
                    'hot',
                    'categories',
                    'stories',
                    'generated_at',
                ],
            ]);

        $this->assertSame($article->slug, $response->json('data.featured.slug'));
    }

    /**
     * Category sections carry per-category headlines with the category attached.
     */
    public function test_home_endpoint_buckets_headlines_per_category(): void
    {
        $category = Category::create(['name' => 'Business', 'slug' => 'business']);
        $topic = NewsTopic::factory()->create(['category_id' => $category->id]);
        NewsArticle::factory()->create([
            'topic_id' => $topic->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertStatus(200);

        $business = collect($response->json('data.categories'))->firstWhere('slug', 'business');

        $this->assertNotNull($business, 'business category must be in the batched payload.');
        $this->assertCount(1, $business['headlines'], 'Category section carries its headline.');
        $this->assertSame('Business', $business['name']);
    }
}
