<?php

namespace App\News\Sources\Support;

use Illuminate\Support\Str;

/**
 * Shared headline tokenizer — stopwords + lowercase + non-alnum strip + length filter.
 * Used by GoogleNewsRssSource, BraveSearchSource, and GdeltSource to produce
 * consistent Jaccard-clustering tokens.
 */
final class HeadlineTokenizer
{
    /** @var array<int, string> */
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'how', 'in', 'is', 'it', 'its',
        'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'why', 'with',
        'today', 'latest', 'live', 'update', 'updates', 'news',
    ];

    /**
     * @return array<int, string>
     */
    public static function tokenize(string $headline): array
    {
        $text = Str::of($headline)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();

        $tokens = explode(' ', $text);

        return array_values(array_unique(array_filter($tokens, function ($token) {
            return $token !== '' && ! in_array($token, self::STOP_WORDS, true) && strlen($token) > 2;
        })));
    }
}
