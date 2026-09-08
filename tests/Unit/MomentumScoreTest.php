<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ArticleRankingService;
use Tests\TestCase;

class MomentumScoreTest extends TestCase
{
    private ArticleRankingService $ranking;

    private const HALF_LIFE = 6.0;

    private const WEIGHTS = [1 => 3.0, 6 => 1.5, 24 => 1.0];

    private const QUALITY_BOOST = 2.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ranking = new ArticleRankingService;
    }

    /**
     * Default-config momentum for a pure 1h surge of 100 views.
     * 100 × 3.0 × 0.5^(0.5/6) = 100 × 3.0 × 0.94387 ≈ 283.2
     */
    public function test_momentum_100_views_1h_only(): void
    {
        $m = $this->ranking->momentum([1 => 100], self::HALF_LIFE, self::WEIGHTS);

        $this->assertEqualsWithDelta(283.1, $m, 0.5);
    }

    /**
     * 100 views spread only in the 6h window.
     * 100 × 1.5 × 0.5^(3/6) = 100 × 1.5 × 0.70711 ≈ 106.1
     */
    public function test_momentum_100_views_6h_only(): void
    {
        $m = $this->ranking->momentum([6 => 100], self::HALF_LIFE, self::WEIGHTS);

        $this->assertEqualsWithDelta(106.1, $m, 0.5);
    }

    /**
     * 100 views spread only in the 24h window.
     * 100 × 1.0 × 0.5^(12/6) = 100 × 1.0 × 0.25 = 25.0
     */
    public function test_momentum_100_views_24h_only(): void
    {
        $m = $this->ranking->momentum([24 => 100], self::HALF_LIFE, self::WEIGHTS);

        $this->assertEqualsWithDelta(25.0, $m, 0.5);
    }

    /**
     * A 1h surge must outweigh a 24h spread of the same view count.
     */
    public function test_1h_surge_outweighs_24h_spread(): void
    {
        $surge = $this->ranking->momentum([1 => 100], self::HALF_LIFE, self::WEIGHTS);
        $spread = $this->ranking->momentum([24 => 100], self::HALF_LIFE, self::WEIGHTS);

        $this->assertGreaterThan($spread, $surge);
    }

    /**
     * Ratio-bug fix: an article with 500 recent + 5000 lifetime views should
     * score HIGHER than one with 2 recent + 2 lifetime, because momentum weighs
     * absolute recent volume, not a velocity ratio that flattens surges.
     */
    public function test_momentum_rewards_absolute_recent_volume_over_ratio(): void
    {
        $highVolume = $this->ranking->momentum([1 => 500], self::HALF_LIFE, self::WEIGHTS);
        $lowVolume = $this->ranking->momentum([1 => 2], self::HALF_LIFE, self::WEIGHTS);

        $this->assertGreaterThan($lowVolume, $highVolume);
    }

    /**
     * Age decay: a 2h-old article with the same momentum outranks a 30h-old one.
     */
    public function test_trending_score_decays_with_age(): void
    {
        $momentum = 100.0;

        $fresh = $this->ranking->trendingScore(
            $momentum, 1.0, false, self::QUALITY_BOOST, 2.0, self::HALF_LIFE,
        );
        $old = $this->ranking->trendingScore(
            $momentum, 1.0, false, self::QUALITY_BOOST, 30.0, self::HALF_LIFE,
        );

        $this->assertGreaterThan($old, $fresh);
    }

    /**
     * Trending score multiplies in the category weight.
     */
    public function test_trending_score_includes_category_weight(): void
    {
        $base = $this->ranking->trendingScore(
            100.0, 1.0, false, self::QUALITY_BOOST, 0.0, self::HALF_LIFE,
        );
        $national = $this->ranking->trendingScore(
            100.0, 2.0, false, self::QUALITY_BOOST, 0.0, self::HALF_LIFE,
        );

        $this->assertSame($base * 2.0, $national);
    }

    /**
     * Trending score applies the quality boost for passed articles.
     */
    public function test_trending_score_includes_quality_boost(): void
    {
        $failed = $this->ranking->trendingScore(
            100.0, 1.0, false, self::QUALITY_BOOST, 0.0, self::HALF_LIFE,
        );
        $passed = $this->ranking->trendingScore(
            100.0, 1.0, true, self::QUALITY_BOOST, 0.0, self::HALF_LIFE,
        );

        // (1 + 2×1) / (1 + 2×0) = 3× boost
        $this->assertSame($failed * 3.0, $passed);
    }
}
