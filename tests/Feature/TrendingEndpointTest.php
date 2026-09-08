<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrendingEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * After recompute, the trending endpoint returns the momentum-heavy article
     * (recent 1h views) first, and excludes articles below the momentum floor.
     */
    public function test_trending_returns_momentum_leader_and_excludes_cold_articles(): void
    {
        // Article A — 50 views in the last hour (strong 1h momentum).
        $a = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);
        $this->seedViews($a->id, 50, now()->subMinutes(30));

        // Article B — 60 views but all >6h ago (below momentum floor after decay).
        $b = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHours(20),
        ]);
        $this->seedViews($b->id, 60, now()->subHours(12));

        // Article C — no views at all.
        NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $this->artisan('news:recompute-rankings')->assertSuccessful();

        $response = $this->getJson('/api/v1/articles/trending');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['slug']]]);

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains($a->slug, $slugs, 'Momentum leader A must appear in trending.');
        $this->assertSame($a->slug, $slugs[0], 'A must be the first trending article.');
        $this->assertNotContains($b->slug, $slugs, 'Stale-spread B must be excluded.');
    }

    private function seedViews(int $articleId, int $count, \Illuminate\Support\Carbon $viewedAt): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'article_id' => $articleId,
                'viewed_at' => $viewedAt->toDateTimeString(),
            ];
        }
        DB::table('article_views')->insert($rows);
    }
}
