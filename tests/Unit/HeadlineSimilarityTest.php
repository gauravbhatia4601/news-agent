<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\News\Support\HeadlineSimilarity;
use Tests\TestCase;

class HeadlineSimilarityTest extends TestCase
{
    /**
     * The real duplicate pair from the production bug: same event, reworded
     * lead — raw-token Jaccard scores ~0.2 but stemmed overlap catches it.
     */
    public function test_real_duplicate_pair_is_similar(): void
    {
        $a = 'Trump pledges $5,000 dividend to Americans if Republicans win midterms';
        $b = 'Donald Trump pledges to give every US adult $5,000 specifically if the GOP wins the midterm elections';

        // overlap coefficient = 4/8 = 0.625 (intersection: trump, pledge, 000, win, midterm)
        $this->assertTrue(HeadlineSimilarity::similar($a, $b));
    }

    /**
     * A genuine new development shares the subject but not the substance —
     * overlap is low and the pair must NOT be flagged as duplicate.
     */
    public function test_genuine_development_pair_is_not_similar(): void
    {
        $a = 'Trump pledges $5,000 dividend to Americans if Republicans win midterms';
        $b = 'Critics condemn Trump\'s $5,000 payout promise as the "language of bribery"';

        // overlap coefficient = 2/8 = 0.25 (intersection: trump, 000)
        $this->assertFalse(HeadlineSimilarity::similar($a, $b));
    }

    /**
     * Possessive + plural variants must normalize to the same stem and
     * register as similar.
     */
    public function test_possessive_and_plural_variants_are_similar(): void
    {
        $a = 'Trump\'s $5,000 Midterm Payout Promise Controversy';
        $b = 'Trump Promises $5,000 Dividend for Midterm Wins';

        // overlap coefficient = 4/6 = 0.6667 (intersection: trump, 000, midterm, promise)
        $this->assertTrue(HeadlineSimilarity::similar($a, $b));
    }

    public function test_empty_strings_are_not_similar(): void
    {
        $this->assertFalse(HeadlineSimilarity::similar('', 'anything'));
        $this->assertFalse(HeadlineSimilarity::similar('anything', ''));
    }

    public function test_single_shared_token_is_not_similar(): void
    {
        // Only "trump" overlaps — intersection = 1, below the ≥2 floor.
        $this->assertFalse(HeadlineSimilarity::similar('Trump signs executive order', 'Trump visits Texas rally'));
    }
}
