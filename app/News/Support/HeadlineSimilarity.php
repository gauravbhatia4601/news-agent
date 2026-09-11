<?php

declare(strict_types=1);

namespace App\News\Support;

/**
 * Stemmed-overlap-coefficient headline similarity.
 *
 * Used as the repetition gate in the live-story monitor and the detection
 * command, where raw-token Jaccard fails on headline drift (plurals,
 * possessives, reworded leads) that the LLM judge also misses.
 *
 * Intentionally does NOT touch HeadlineTokenizer::tokenize() — story topic
 * signatures hash those tokens (sha1("story:{id}|coreTokens")), so changing
 * HeadlineTokenizer would break existing signatures.
 */
final class HeadlineSimilarity
{
    /** @var array<int, string> */
    private const STOPWORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'how',
        'in', 'is', 'it', 'its', 'of', 'on', 'or', 'that', 'the', 'this', 'to',
        'was', 'what', 'when', 'where', 'who', 'why', 'with',
        'today', 'latest', 'live', 'update', 'updates', 'news',
        'has', 'have', 'had', 'will', 'would', 'could', 'should',
        'may', 'might', 'can', 'do', 'does', 'did', 'not', 'no',
        'but', 'if', 'then', 'than', 'so', 'such', 'also', 'about',
        'into', 'after', 'before', 'during', 'while', 'which', 'whom',
        'whose', 'his', 'her', 'their', 'them', 'they', 'you', 'your',
        'our', 'we', 'us', 'she', 'him',
    ];

    /**
     * Tokenize + stem: lowercase, strip publisher suffixes, strip non-alnum,
     * drop short purely-alpha tokens (≤2 chars) but keep digit-containing
     * tokens of any length (6, 1b, 5000, 2026), drop stopwords, strip
     * possessive 's, then strip trailing 's' on purely-alpha stems > 3 chars
     * (symmetric: pledges→pledge, midterms→midterm, trumps→trump). Digit
     * tokens are never stemmed (1b → 1 would lose the unit).
     *
     * @return array<int, string>
     */
    public static function tokens(string $text): array
    {
        $lower = mb_strtolower($text);
        $lower = self::stripPublisherSuffix($lower);
        // Normalize currency symbols to words so €6.1B → "euro 6 1b" — the
        // word "euro" becomes a shared token between headlines that reference
        // the same EU amount, lifting overlap above the match threshold.
        $lower = str_replace(
            ['€', '$', '£', '¥', '₹'],
            [' euro ', ' dollar ', ' pound ', ' yen ', ' rupee '],
            $lower,
        );
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $lower) ?? '';
        $rawTokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stemmed = [];
        foreach ($rawTokens as $token) {
            // Keep digit-containing tokens regardless of length (6, 1b, 5000);
            // drop short purely-alpha tokens (≤2 chars).
            if (strlen($token) <= 2 && ! preg_match('/\d/', $token)) {
                continue;
            }
            if (in_array($token, self::STOPWORDS, true)) {
                continue;
            }

            $stem = $token;
            // Strip trailing 's' for plural/possessive normalization, but
            // only on purely-alpha stems long enough to survive meaningfully
            // — never on digit-containing tokens (1b → 1 would lose the unit).
            if (strlen($stem) > 3 && str_ends_with($stem, 's') && ! preg_match('/\d/', $stem)) {
                $stem = substr($stem, 0, -1);
            }

            // Final length gate mirrors the initial filter: keep digit tokens
            // even when short (5, 1, 0); drop short alpha remnants.
            if ($stem !== '' && (strlen($stem) > 2 || preg_match('/\d/', $stem))) {
                $stemmed[] = $stem;
            }
        }

        return array_values(array_unique($stemmed));
    }

    /**
     * Strip trailing publisher suffixes from Google News headlines
     * ("Headline - Publisher"). Cuts at the last " - ", " – ", " — ", or " | "
     * when it sits in the last ~40% of the string — avoids chopping legit
     * mid-headline dashes.
     */
    private static function stripPublisherSuffix(string $lower): string
    {
        $separators = [' - ', ' – ', ' — ', ' | '];
        $len = mb_strlen($lower);
        if ($len === 0) {
            return $lower;
        }

        $cutPoint = -1;
        foreach ($separators as $sep) {
            $pos = mb_strrpos($lower, $sep);
            if ($pos === false) {
                continue;
            }
            // Only strip when the separator is in the last ~40% of the string.
            if ($pos >= (int) ($len * 0.6) && $pos > $cutPoint) {
                $cutPoint = $pos;
            }
        }

        if ($cutPoint >= 0) {
            return trim(mb_substr($lower, 0, $cutPoint));
        }

        return $lower;
    }

    /**
     * Overlap coefficient = |A ∩ B| / min(|A|, |B|) on stemmed tokens.
     *
     * Returns true when the coefficient ≥ threshold AND the intersection is
     * at least 2 tokens (avoids spurious matches on a single shared stem).
     */
    public static function similar(string $a, string $b, float $threshold = 0.5): bool
    {
        $tokensA = self::tokens($a);
        $tokensB = self::tokens($b);

        if ($tokensA === [] || $tokensB === []) {
            return false;
        }

        $setA = array_unique($tokensA);
        $setB = array_unique($tokensB);
        $intersection = count(array_intersect($setA, $setB));
        $minSize = min(count($setA), count($setB));

        if ($intersection < 2) {
            return false;
        }

        return ($intersection / $minSize) >= $threshold;
    }
}
