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
     * Tokenize + stem: lowercase, strip non-alnum, drop ≤2-char tokens and
     * stopwords, strip possessive 's, then strip trailing 's' on stems > 3
     * chars (symmetric: pledges→pledge, midterms→midterm, trumps→trump).
     *
     * @return array<int, string>
     */
    public static function tokens(string $text): array
    {
        $lower = mb_strtolower($text);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $lower) ?? '';
        $rawTokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stemmed = [];
        foreach ($rawTokens as $token) {
            if (strlen($token) <= 2) {
                continue;
            }
            if (in_array($token, self::STOPWORDS, true)) {
                continue;
            }

            $stem = $token;
            // Strip possessive 's' (handled by non-alnum strip already, but
            // guard for tokens that were exactly "word's" → "words" after strip).
            // Strip trailing 's' when the stem is long enough that the singular
            // form survives meaningfully (length > 3 after the 's' is dropped).
            if (strlen($stem) > 3 && str_ends_with($stem, 's')) {
                $stem = substr($stem, 0, -1);
            }

            if ($stem !== '' && strlen($stem) > 2) {
                $stemmed[] = $stem;
            }
        }

        return array_values(array_unique($stemmed));
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
