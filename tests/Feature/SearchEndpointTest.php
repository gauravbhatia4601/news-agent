<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * sqlite suite exercises the ilike fallback branch (no tsvector on sqlite);
     * the Postgres FTS branch is verified live post-deploy.
     */
    public function test_search_returns_matching_articles(): void
    {
        NewsArticle::factory()->create([
            'title' => 'Parliament passes landmark election reform bill',
            'slug' => 'election-reform-passed',
        ]);
        NewsArticle::factory()->create([
            'title' => 'Monsoon arrival shifts a week early this year',
            'slug' => 'monsoon-shift',
        ]);

        $response = $this->getJson('/api/v1/articles/search?q=election+reform&per_page=5');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('election-reform-passed', $slugs);
        $this->assertNotContains('monsoon-shift', $slugs);
    }

    public function test_search_rejects_single_character_query(): void
    {
        NewsArticle::factory()->count(3)->create(['title' => 'Generic title with letters']);

        // Validation guard (422) + repository guard (empty 200) both reject it —
        // either layer protects the corpus from single-character scans.
        $response = $this->getJson('/api/v1/articles/search?q=a&per_page=5');

        $response->assertStatus(422);
    }

    public function test_search_per_page_is_capped(): void
    {
        NewsArticle::factory()->count(5)->create(['title' => 'Reform bill passes parliament again']);

        $response = $this->getJson('/api/v1/articles/search?q=reform&per_page=100');

        $response->assertOk();
        $this->assertLessThanOrEqual(30, count($response->json('data')));
    }

    public function test_search_respects_category_filter(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        // topic_id is unique on news_articles — one topic per article.
        $inTopic = NewsTopic::factory()->create(['category_id' => $category->id]);
        NewsArticle::factory()->create([
            'topic_id' => $inTopic->id,
            'title' => 'Election reform bill advances',
            'slug' => 'reform-in-cat',
        ]);

        $outTopic = NewsTopic::factory()->create();
        NewsArticle::factory()->create([
            'topic_id' => $outTopic->id,
            'title' => 'Election reform debated abroad',
            'slug' => 'reform-elsewhere',
        ]);

        $response = $this->getJson('/api/v1/articles/search?q=election&per_page=10&category=technology');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('reform-in-cat', $slugs);
        $this->assertNotContains('reform-elsewhere', $slugs);
    }
}
