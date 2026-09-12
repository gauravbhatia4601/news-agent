<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\News\Support\MetaClamp;
use Tests\TestCase;

/**
 * Verifies the title/meta_description clamping that prevents
 * "Title too long" and "Meta description too long" SEO issues.
 * Tests the shared MetaClamp helper — no DB, no LLM.
 */
class SeoClampTest extends TestCase
{
    public function test_short_title_passes_through_unchanged(): void
    {
        $this->assertSame('India GDP Growth Hits 6.5%', MetaClamp::clamp('India GDP Growth Hits 6.5%', 60));
    }

    public function test_long_title_clamped_to_60_at_word_boundary(): void
    {
        $longTitle = 'India GDP Growth Surges to 6.5 Percent in Q3 2024 Beating Economist Estimates and Market Predictions';
        $result = MetaClamp::clamp($longTitle, 60);

        $this->assertLessThanOrEqual(61, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
        $this->assertStringNotContainsString('Economist', $result);
        $this->assertStringNotContainsString('Predictions', $result);
    }

    public function test_title_exactly_60_chars_unchanged(): void
    {
        $exact = str_repeat('a', 60);
        $this->assertSame($exact, MetaClamp::clamp($exact, 60));
    }

    public function test_meta_description_clamped_to_155(): void
    {
        $longDesc = str_repeat('The quick brown fox jumps. ', 10);
        $result = MetaClamp::clamp($longDesc, 155);

        $this->assertLessThanOrEqual(156, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function test_empty_title_returns_empty(): void
    {
        $this->assertSame('', MetaClamp::clamp('', 60));
    }

    public function test_title_with_no_spaces_before_cutoff_hard_cut(): void
    {
        $longWord = str_repeat('x', 80);
        $result = MetaClamp::clamp($longWord, 60);

        $this->assertSame(60, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function test_clamp_title_helper_clamps_to_60(): void
    {
        $long = str_repeat('word ', 20);
        $result = MetaClamp::clampTitle($long);

        $this->assertLessThanOrEqual(61, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function test_clamp_description_helper_clamps_to_155(): void
    {
        $long = str_repeat('sentence. ', 30);
        $result = MetaClamp::clampDescription($long);

        $this->assertLessThanOrEqual(156, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function test_category_description_produces_100_to_155_chars(): void
    {
        $desc = MetaClamp::categoryDescription('Technology');

        $this->assertGreaterThanOrEqual(100, mb_strlen($desc));
        $this->assertLessThanOrEqual(155, mb_strlen($desc));
        $this->assertStringContainsString('Technology', $desc);
    }

    public function test_category_description_long_name_clamped(): void
    {
        $longName = 'Artificial Intelligence and Machine Learning Research';
        $desc = MetaClamp::categoryDescription($longName);

        $this->assertLessThanOrEqual(155, mb_strlen($desc));
    }

    public function test_extract_first_sentence_strips_markdown(): void
    {
        $content = "## Heading\n\n**Bold** text about [AI](https://example.com) developments. Second sentence here.\n\nMore text.";
        $result = MetaClamp::extractFirstSentence($content);

        $this->assertStringNotContainsString('##', $result);
        $this->assertStringNotContainsString('[', $result);
        $this->assertStringNotContainsString('**', $result);
        $this->assertNotEmpty($result);
    }

    public function test_extract_first_sentence_strips_html(): void
    {
        $content = '<p>Breaking news about a major event. Details emerging.</p><p>More.</p>';
        $result = MetaClamp::extractFirstSentence($content);

        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('</p>', $result);
    }

    public function test_extract_first_sentence_empty_returns_empty(): void
    {
        $this->assertSame('', MetaClamp::extractFirstSentence(''));
        $this->assertSame('', MetaClamp::extractFirstSentence('   '));
    }
}
