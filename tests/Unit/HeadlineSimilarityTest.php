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

        // overlap coefficient = 7/10 = 0.7 (intersection: trump, pledge, dollar, 5, 000, win, midterm)
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

        // overlap coefficient = 4/10 = 0.4 (intersection: trump, dollar, 5, 000)
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

        // overlap coefficient = 6/8 = 0.75 (intersection: trump, promise, dollar, 5, 000, midterm)
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

    /**
     * The verified production failure case: same EU event, different
     * publishers, one with "€6.1B" and the other with "€6.1 billion".
     * Before the fix, publisher-suffix junk and dropped digit tokens kept
     * overlap below 0.6 and the pair spawned a duplicate topic row.
     */
    public function test_eu_patriot_pair_is_similar_at_06(): void
    {
        $a = 'EU Clears €6.1B Tranche for Patriot Missiles and Drones - streamlinefeed.co.ke';
        $b = "EU Commission approves €6.1 billion for Ukraine's Patriot missiles and drones - RBC-Ukraine";

        // After suffix stripping + digit keeping + currency normalization:
        // intersection = {euro, 6, patriot, missile, drone} = 5, overlap = 5/8 = 0.625
        $this->assertTrue(HeadlineSimilarity::similar($a, $b, 0.6));
    }

    /**
     * Manually stripping the publisher suffixes must yield the same result
     * as the tokenizer's automatic stripping — the stripping is idempotent.
     */
    public function test_eu_pair_with_manual_suffix_strip_is_similar_at_06(): void
    {
        $a = 'EU Clears €6.1B Tranche for Patriot Missiles and Drones';
        $b = "EU Commission approves €6.1 billion for Ukraine's Patriot missiles and drones";

        $this->assertTrue(HeadlineSimilarity::similar($a, $b, 0.6));
    }

    /**
     * Suffix stripping must only cut the trailing segment — a headline with
     * a dash in the middle keeps the mid-headline portion.
     */
    public function test_suffix_stripping_only_cuts_trailing_segment(): void
    {
        // "Apple - Google deal - Reuters" → strips " - Reuters" only,
        // keeping "Apple - Google deal" → tokens: apple, google, deal.
        $tokens = HeadlineSimilarity::tokens('Apple - Google deal - Reuters');

        $this->assertContains('apple', $tokens);
        $this->assertContains('google', $tokens);
        $this->assertContains('deal', $tokens);
        $this->assertNotContains('reuters', $tokens);
    }

    /**
     * A genuinely distinct pair that shares an entity and numbers but
     * describes different events must NOT be flagged as similar.
     */
    public function test_distinct_events_with_shared_numbers_not_similar(): void
    {
        $a = 'EU approves €6.1 billion for Patriot missiles';
        $b = 'EU rejects €6.1 billion infrastructure bill';

        // Shares euro, 6, 1, billion but the overlap is low relative to
        // the smaller set's size — not the same event.
        $this->assertFalse(HeadlineSimilarity::similar($a, $b, 0.6));
    }
}
