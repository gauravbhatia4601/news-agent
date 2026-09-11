<?php

declare(strict_types=1);

namespace App\News\Support;

/**
 * Mechanical source-grounding backstop for article generation.
 *
 * Runs AFTER the LLM produces an article, BEFORE it is saved. Catches three
 * hallucination classes that prompt rules alone cannot guarantee:
 *   1. Fabricated direct quotes attributed to real named officials.
 *   2. Invented headline statistics with no basis in any source.
 *   3. Hallucinated entities / specifics presented as fact.
 *
 * Quote verification is deterministic and strips ungrounded quote-sentences.
 * Number-claim checking is soft (log-only) — too many false positives to block.
 */
final class SourceGroundingChecker
{
    /** Quotes shorter than 10 chars or longer than 300 are ignored. */
    private const MIN_QUOTE_LEN = 10;

    private const MAX_QUOTE_LEN = 300;

    /** High-similarity threshold for paraphrased-quote grounding. */
    private const SIMILARITY_THRESHOLD = 0.85;

    /**
     * Extract all double-quoted spans (10-300 chars) from the article body.
     *
     * @return list<string>
     */
    public function extractQuotedSpans(string $markdown): array
    {
        preg_match_all('/"([^"]{'.self::MIN_QUOTE_LEN.','.self::MAX_QUOTE_LEN.'})"/', $markdown, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Verify every quoted span against the concatenated source text.
     * Ungrounded quote-sentences are stripped from the markdown.
     *
     * @return array{0: string, 1: int} [cleaned_markdown, stripped_sentence_count]
     */
    public function verifyQuotes(string $markdown, string $sourceText): array
    {
        $quotes = $this->extractQuotedSpans($markdown);
        if ($quotes === []) {
            return [$markdown, 0];
        }

        $normalizedSource = $this->normalize($sourceText);
        $stripped = 0;
        $cleaned = $markdown;

        foreach ($quotes as $quote) {
            if ($this->isGrounded($quote, $normalizedSource)) {
                continue;
            }

            // Strip the sentence containing the ungrounded quote — safest,
            // removes the fabricated attribution/context along with the quote.
            $cleaned = $this->stripSentenceContaining($cleaned, $quote);
            $stripped++;

            \Log::warning('Ungrounded quote stripped from generated article.', [
                'quote' => mb_substr($quote, 0, 200),
                'reason' => 'quote not found in source content (verbatim or high-similarity)',
            ]);
        }

        return [$cleaned, $stripped];
    }

    /**
     * Soft check: log any numeric claim not found in source text.
     * Never blocks — for observability of hallucinated statistics.
     */
    public function checkNumberClaims(string $markdown, string $sourceText, int $topicId = 0): void
    {
        $normalizedSource = $this->normalize($sourceText);
        $pattern = '/\b\d+(?:\.\d+)?\s?%(?:\s?of\s\w+)?|\b\d+\s?(?:billion|million|thousand|crore|lakh)\b|\bUSD\s?\d+\s?(?:billion|million|thousand)\b|\b₹\s?\d+\s?(?:crore|lakh|billion|million)\b/i';

        preg_match_all($pattern, $markdown, $matches);
        $claims = array_values(array_unique($matches[0] ?? []));

        foreach ($claims as $claim) {
            $normalizedClaim = $this->normalize($claim);
            if ($normalizedClaim !== '' && ! str_contains($normalizedSource, $normalizedClaim)) {
                \Log::warning('Numeric claim in article not found in sources.', [
                    'topic_id' => $topicId,
                    'claim' => $claim,
                    'reason' => 'number not grounded in source content (observability only, not blocked)',
                ]);
            }
        }
    }

    /**
     * Is a quoted span grounded in the source text?
     * Grounded = verbatim substring match (after normalization) OR
     * high-similarity token overlap (catches minor paraphrasing/punctuation drift).
     */
    private function isGrounded(string $quote, string $normalizedSource): bool
    {
        $normalizedQuote = $this->normalize($quote);
        if ($normalizedQuote === '') {
            return true; // empty after normalization — nothing to verify
        }

        // Verbatim substring match after normalization.
        if (str_contains($normalizedSource, $normalizedQuote)) {
            return true;
        }

        // High-similarity fallback: catches minor wording/punctuation drift.
        return HeadlineSimilarity::similar($quote, $normalizedSource, self::SIMILARITY_THRESHOLD);
    }

    /**
     * Normalize text for substring matching: lowercase, strip punctuation,
     * collapse whitespace. Preserves digits and alphanumerics.
     */
    private function normalize(string $text): string
    {
        $lower = mb_strtolower($text);
        $stripped = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower) ?? $lower;
        $collapsed = preg_replace('/\s+/u', ' ', $stripped) ?? $stripped;

        return trim($collapsed);
    }

    /**
     * Strip the sentence containing a given substring from markdown.
     * Splits on sentence boundaries (. ! ?) and removes the matching sentence.
     */
    private function stripSentenceContaining(string $markdown, string $quote): string
    {
        // Find the position of the quote in the markdown.
        $pos = mb_strpos($markdown, $quote);
        if ($pos === false) {
            return $markdown;
        }

        // Find sentence boundaries around the quote.
        $before = mb_substr($markdown, 0, $pos);
        $after = mb_substr($markdown, $pos + mb_strlen($quote));

        // Find the start of the sentence (last sentence-ender before the quote).
        $sentenceStart = 0;
        $lastEnder = -1;
        foreach (['. ', '! ', '? ', "\n"] as $ender) {
            $found = mb_strrpos($before, $ender);
            if ($found !== false && $found > $lastEnder) {
                $lastEnder = $found;
                $sentenceStart = $found + mb_strlen($ender);
            }
        }

        // Find the end of the sentence (first sentence-ender after the quote).
        $sentenceEnd = mb_strlen($markdown);
        foreach (['. ', '! ', '? ', "\n"] as $ender) {
            $found = mb_strpos($after, $ender);
            if ($found !== false && $found + mb_strlen($ender) < $sentenceEnd) {
                $sentenceEnd = $pos + mb_strlen($quote) + $found + mb_strlen($ender);
            }
        }

        $removed = mb_substr($markdown, $sentenceStart, $sentenceEnd - $sentenceStart);
        $cleaned = mb_substr($markdown, 0, $sentenceStart).mb_substr($markdown, $sentenceEnd);

        // Clean up: collapse multiple blank lines left behind.
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned) ?? $cleaned;

        return trim($cleaned);
    }
}
