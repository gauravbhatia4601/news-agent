<?php

declare(strict_types=1);

namespace App\News\Support;

/**
 * Shared word-boundary truncation for SEO meta fields.
 * Used by both article generation and the regenerate-article-meta command
 * to keep title/description lengths within SEO limits.
 */
final class MetaClamp
{
    /**
     * Truncate a string to $max chars at word boundary (whole-word safe).
     * Never splits a word in half; appends an ellipsis when truncated.
     */
    public static function clamp(string $text, int $max): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace === false || $lastSpace < $max * 0.6) {
            return mb_substr($text, 0, $max - 1).'…';
        }

        return mb_substr($cut, 0, $lastSpace).'…';
    }

    /**
     * Clamp to title length (60 chars, Google's display limit).
     */
    public static function clampTitle(string $title): string
    {
        return self::clamp($title, 60);
    }

    /**
     * Clamp to meta description length (155 chars, common SEO target).
     */
    public static function clampDescription(string $description): string
    {
        return self::clamp($description, 155);
    }

    /**
     * Extract the first meaningful sentence from article content,
     * stripped of markdown/HTML, for use as a meta description.
     */
    public static function extractFirstSentence(string $content): string
    {
        // Strip HTML tags
        $text = strip_tags($content);
        // Strip common markdown
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text) ?? $text;
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text) ?? $text;
        $text = preg_replace('/\[source\]|\[citation\]|\[\d+\]/i', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        // Cut at first sentence boundary (. ! ?) followed by space or end.
        if (preg_match('/^(.{20,}?[.!?])(?:\s|$)/u', $text, $m)) {
            return trim($m[1]);
        }

        // No sentence boundary — return up to first 200 chars as raw.
        return mb_substr($text, 0, 200);
    }

    /**
     * Build a deterministic meta description from a category name.
     * Produces 100-155 chars by constructing a longer sentence.
     */
    public static function categoryDescription(string $categoryName): string
    {
        $name = ucfirst(trim($categoryName));
        $base = "Read the latest {$name} news, in-depth analysis, and breaking updates from The Neural Journal. ";
        $tail = 'Stay informed with curated, fact-driven coverage of developing stories and trending topics.';

        $full = $base.$tail;
        if (mb_strlen($full) <= 155) {
            return $full;
        }

        return self::clampDescription($full);
    }
}
