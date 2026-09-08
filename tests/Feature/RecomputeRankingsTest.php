<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecomputeRankingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A recently-viewed article gets a positive momentum_score and populated
     * views_1h; an old no-views article stays at zero on the first run.
     */
    public function test_recompute_writes_momentum_and_window_counts(): void
    {
        $recent = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);
        $this->seedViews($recent->id, 20, now()->subMinutes(20));

        $old = NewsArticle::factory()->create([
            'status' => 'published',
            'published_at' => now()->subDays(10),
        ]);

        $this->artisan('news:recompute-rankings')->assertSuccessful();

        $recentFresh = $recent->fresh();
        $oldFresh = $old->fresh();

        $this->assertGreaterThan(0, $recentFresh->momentum_score, 'Recent article must have positive momentum.');
        $this->assertSame(20, $recentFresh->views_1h, 'views_1h must hold the raw 1h window count.');

        // No views and outside the recompute window → momentum stays 0 (nothing to decay on first run).
        $this->assertSame(0.0, (float) $oldFresh->momentum_score, 'Cold old article must stay at zero momentum.');
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
