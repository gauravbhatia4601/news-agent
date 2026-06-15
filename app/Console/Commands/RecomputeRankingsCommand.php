<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\NewsArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecomputeRankingsCommand extends Command
{
    protected $signature = 'news:recompute-rankings';
    protected $description = 'Recompute hot_score and view_velocity for all published articles';

    private const CATEGORY_WEIGHTS = [
        'national' => 2.0,
        'world' => 1.8,
        'business-economy' => 1.5,
        'technology' => 1.5,
        'science-education' => 1.3,
        'sports' => 1.0,
        'entertainment' => 1.0,
        'lifestyle' => 0.8,
    ];

    public function handle(): int
    {
        $now = now();
        $last24h = $now->clone()->subHours(24);

        $articles = NewsArticle::with('topic.categoryRelation.parent')
            ->where('status', 'published')
            ->get();

        $bar = $this->output->createProgressBar($articles->count());
        $updated = 0;

        foreach ($articles as $article) {
            $hoursSincePublish = max(0, $article->created_at->diffInHours($now, true));
            $totalViews = max((int) $article->views, 1);

            $recentViews = DB::table('article_views')
                ->where('article_id', $article->id)
                ->where('viewed_at', '>=', $last24h)
                ->count();

            $velocity = $totalViews > 0 ? $recentViews / $totalViews : 0;

            $qualityPassed = 0;
            if ($article->quality_report) {
                $qr = json_decode($article->quality_report, true);
                if (is_array($qr) && empty($qr['issues'] ?? [])) {
                    $qualityPassed = 1;
                }
            }

            $parentSlug = $article->topic?->categoryRelation?->parent?->slug
                ?? $article->topic?->categoryRelation?->slug
                ?? '';

            $categoryWeight = self::CATEGORY_WEIGHTS[$parentSlug] ?? 1.0;

            $hotScore = (
                log10($totalViews)
                + 2 * $qualityPassed
                + $categoryWeight
            ) / pow($hoursSincePublish + 2, 1.5);

            $article->update([
                'hot_score' => round($hotScore, 6),
                'view_velocity' => round($velocity, 4),
            ]);

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$updated} articles.");

        return self::SUCCESS;
    }
}
