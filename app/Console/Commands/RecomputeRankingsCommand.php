<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NewsArticle;
use App\Services\ArticleRankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecomputeRankingsCommand extends Command
{
    protected $signature = 'news:recompute-rankings {--full}';

    protected $description = 'Recompute momentum_score, windowed view counts, and hot_score for recently active or all published articles';

    public function handle(ArticleRankingService $ranking): int
    {
        $config = config('news-engine.ranking');
        $halfLife = (float) $config['half_life_hours'];
        $windowWeights = $config['window_weights'];
        $windows = $config['windows'];
        $qualityBoost = (float) $config['quality_boost'];
        $categoryWeights = $config['category_weights'];
        $hotGravity = (float) $config['hot_gravity'];
        $recentWindowHours = (float) $config['recompute_recent_window_hours'];
        $trendingMaxAgeHours = (float) $config['trending_max_age_hours'];

        $now = now();
        $recomputeCutoff = $now->clone()->subHours($recentWindowHours);
        $recentAgeCutoff = $now->clone()->subHours($trendingMaxAgeHours);

        $ids = $this->selectArticleIds($recomputeCutoff, $recentAgeCutoff);
        $totalCount = $ids->count();

        if ($totalCount === 0) {
            // Nothing freshly selected — still run the decay pass so stale
            // momentum fades during quiet periods (all articles are "not recomputed").
            $this->applyBackgroundDecay([], $halfLife, $ranking);
            $this->info('No articles to recompute; background decay applied.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();
        $updated = 0;
        $recomputedIds = [];

        // Per-window cutoff timestamps reused across chunks.
        $cutoffs = [];
        foreach ($windows as $w) {
            $cutoffs[$w] = $now->clone()->subHours((int) $w);
        }

        $ids->chunk(500)->each(function (Collection $chunk) use (
            $ranking, $halfLife, $windowWeights, $qualityBoost,
            $categoryWeights, $hotGravity, $now, $cutoffs,
            &$bar, &$updated, &$recomputedIds
        ) {
            $chunkIds = $chunk->all();
            $recomputedIds = array_merge($recomputedIds, $chunkIds);

            // Three grouped COUNT queries — one per window — then map article_id => count.
            $windowCounts = [];
            foreach ($cutoffs as $windowHours => $cutoff) {
                $rows = DB::table('article_views')
                    ->select('article_id', DB::raw('COUNT(*) as cnt'))
                    ->where('viewed_at', '>=', $cutoff)
                    ->whereIn('article_id', $chunkIds)
                    ->groupBy('article_id')
                    ->pluck('cnt', 'article_id');

                $windowCounts[$windowHours] = $rows;
            }

            $articles = NewsArticle::with('topic.categoryRelation.parent')
                ->whereIn('id', $chunkIds)
                ->get();

            foreach ($articles as $article) {
                $counts = [];
                foreach ($cutoffs as $windowHours => $_) {
                    $counts[$windowHours] = (int) ($windowCounts[$windowHours][$article->id] ?? 0);
                }

                $momentum = $ranking->momentum($counts, $halfLife, $windowWeights);

                $qualityPassed = $this->qualityPassed($article);

                $parentSlug = $article->topic?->categoryRelation?->parent?->slug
                    ?? $article->topic?->categoryRelation?->slug
                    ?? '';
                $categoryWeight = (float) ($categoryWeights[$parentSlug] ?? 1.0);

                $publishedAt = $article->published_at ?? $article->created_at;
                $ageHours = max(0.0, $publishedAt->diffInHours($now, true));

                $trendingScore = $ranking->trendingScore(
                    $momentum, $categoryWeight, $qualityPassed,
                    $qualityBoost, $ageHours, $halfLife,
                );

                $hotScore = $ranking->hotScore(
                    $momentum, $qualityPassed, $categoryWeight, $ageHours, $hotGravity,
                );

                $article->update([
                    'momentum_score' => round($trendingScore, 6),
                    'hot_score' => round($hotScore, 6),
                    'views_1h' => $counts[1] ?? 0,
                    'views_6h' => $counts[6] ?? 0,
                    'views_24h' => $counts[24] ?? 0,
                ]);

                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$updated} articles.");

        // Background decay for published articles not recomputed this run.
        $this->applyBackgroundDecay($recomputedIds, $halfLife, $ranking);

        return self::SUCCESS;
    }

    /**
     * Select article IDs to recompute: --full = all published; otherwise the
     * union of articles viewed in the recent window and articles published
     * within the trending max-age window.
     *
     * @return Collection<int, int>
     */
    private function selectArticleIds(Carbon $recomputeCutoff, Carbon $recentAgeCutoff): Collection
    {
        if ($this->option('full')) {
            return NewsArticle::where('status', 'published')->pluck('id');
        }

        $viewedIds = DB::table('article_views')
            ->select('article_id')
            ->distinct()
            ->where('viewed_at', '>=', $recomputeCutoff)
            ->pluck('article_id');

        $recentlyPublishedIds = NewsArticle::where('status', 'published')
            ->where(function ($q) use ($recentAgeCutoff) {
                $q->where('published_at', '>=', $recentAgeCutoff)
                    ->orWhere(function ($q2) use ($recentAgeCutoff) {
                        $q2->whereNull('published_at')
                            ->where('created_at', '>=', $recentAgeCutoff);
                    });
            })
            ->pluck('id');

        return $viewedIds->merge($recentlyPublishedIds)->unique()->values();
    }

    private function qualityPassed(NewsArticle $article): bool
    {
        if (! $article->quality_report) {
            return false;
        }

        $report = is_array($article->quality_report)
            ? $article->quality_report
            : json_decode($article->quality_report, true);

        return is_array($report) && empty($report['issues'] ?? []);
    }

    /**
     * Age out momentum_score for published articles not refreshed this run so
     * yesterday's surge doesn't linger at full strength. Batched to avoid
     * enormous whereNotIn clauses on large catalogs.
     */
    private function applyBackgroundDecay(array $recomputedIds, float $halfLife, ArticleRankingService $ranking): void
    {
        $decay = $ranking->decayFactor($halfLife);
        $threshold = 0.01;

        $query = NewsArticle::where('status', 'published')
            ->where('momentum_score', '>', $threshold);

        if (! empty($recomputedIds)) {
            // ponytail: single whereNotIn — article catalog is tens of thousands at most;
            // if it ever exceeds the DB's IN-list limit, switch to a temp-table join.
            $query->whereNotIn('id', $recomputedIds);
        }

        $query->update(['momentum_score' => DB::raw("momentum_score * {$decay}")]);
    }
}
