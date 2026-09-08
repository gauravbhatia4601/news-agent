<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pure scoring math for momentum-based article ranking.
 *
 * Window counts (views in 1h/6h/24h) are decayed by a half-life curve so a
 * recent surge outweighs an older spread. The resulting momentum is multiplied
 * by category weight, quality boost, and an age-decay factor to produce the
 * trending_score stored in news_articles.momentum_score (ORDER BY that column
 * alone ranks trending articles).
 */
class ArticleRankingService
{
    /**
     * Sum decayed, weighted view counts across sliding windows.
     *
     * Each window W contributes: count_W × weight_W × 0.5^((W/2) / halfLife).
     * The W/2 term is the window's midpoint age — views in a 1h window are on
     * average 30 minutes old; views in a 24h window are on average 12h old.
     *
     * @param  array<int, int>  $windowCounts  Map of window-hours => view count (e.g. [1 => 50, 6 => 120, 24 => 300]).
     * @param  array<int, float>  $windowWeights  Map of window-hours => weight.
     */
    public function momentum(array $windowCounts, float $halfLifeHours, array $windowWeights): float
    {
        $total = 0.0;

        foreach ($windowCounts as $windowHours => $count) {
            if ($count <= 0) {
                continue;
            }

            $weight = $windowWeights[$windowHours] ?? 1.0;
            $midpoint = $windowHours / 2.0;
            $decay = pow(0.5, $midpoint / $halfLifeHours);

            $total += $count * $weight * $decay;
        }

        return $total;
    }

    /**
     * Full trending score: momentum × categoryWeight × (1 + qualityBoost × qualityPassed) × ageDecay.
     *
     * This is the value stored in momentum_score — the repository ORDER BYs it
     * directly, so it must encode every boost and the age decay.
     */
    public function trendingScore(
        float $momentum,
        float $categoryWeight,
        bool $qualityPassed,
        float $qualityBoost,
        float $ageHours,
        float $halfLifeHours,
    ): float {
        $qualityMultiplier = 1.0 + ($qualityBoost * ($qualityPassed ? 1.0 : 0.0));
        $ageDecay = pow(0.5, $ageHours / $halfLifeHours);

        return $momentum * $categoryWeight * $qualityMultiplier * $ageDecay;
    }

    /**
     * Hot score (unchanged shape, gravity now configurable): a Reddit-style
     * log-scaled score that keeps evergreen content from dominating.
     */
    public function hotScore(
        float $momentum,
        bool $qualityPassed,
        float $categoryWeight,
        float $ageHours,
        float $hotGravity,
    ): float {
        $log = log10(max($momentum, 1.0));

        return ($log + (2 * ($qualityPassed ? 1 : 0)) + $categoryWeight)
            / pow($ageHours + 2, $hotGravity);
    }

    /**
     * Background decay applied to published articles not recomputed this run.
     *
     * Each tick ages them by a quarter-hour of half-life so momentum_score
     * trends toward zero between recomputes instead of going stale instantly.
     */
    public function decayFactor(float $halfLifeHours, float $elapsedHours = 0.25): float
    {
        return pow(0.5, $elapsedHours / $halfLifeHours);
    }
}
