<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\News\Support\SourceGroundingChecker;
use Tests\TestCase;

class SourceGroundingCheckerTest extends TestCase
{
    private SourceGroundingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new SourceGroundingChecker;
    }

    public function test_strips_ungrounded_quote_sentence_and_keeps_grounded_one(): void
    {
        $sourceText = 'The minister said "we are committed to reforming the sector by 2026" in a press conference today.';

        $markdown = <<<'MD'
## Reform Push

The minister outlined the timeline clearly. "we are committed to reforming the sector by 2026" he told reporters.

"This is a fabricated quote that appears in no source whatsoever and should be removed entirely" the official continued. The reforms will take effect soon.
MD;

        [$cleaned, $stripped] = $this->checker->verifyQuotes($markdown, $sourceText);

        // The grounded quote-sentence must survive.
        $this->assertStringContainsString('we are committed to reforming the sector by 2026', $cleaned);
        // The ungrounded quote-sentence must be stripped.
        $this->assertStringNotContainsString('fabricated quote that appears in no source', $cleaned);
        // Exactly one sentence stripped.
        $this->assertSame(1, $stripped);
    }

    public function test_keeps_all_quotes_when_everything_is_grounded(): void
    {
        $sourceText = 'Official A said "the policy will take effect in March" and later added "funding has been approved for the full amount"';

        $markdown = <<<'MD'
## Policy Update

"the policy will take effect in March" the official confirmed. "funding has been approved for the full amount" was also announced.
MD;

        [$cleaned, $stripped] = $this->checker->verifyQuotes($markdown, $sourceText);

        $this->assertSame(0, $stripped);
        $this->assertStringContainsString('the policy will take effect in March', $cleaned);
        $this->assertStringContainsString('funding has been approved for the full amount', $cleaned);
    }

    public function test_strips_multiple_ungrounded_quotes(): void
    {
        $sourceText = 'The report mentioned a growth figure of 5 percent.';

        $markdown = <<<'MD'
## Economy

"this is the first fabricated quote in the article" said one analyst. "this is the second fabricated quote also not in sources" another added.

The report mentioned a growth figure of 5 percent.
MD;

        [$cleaned, $stripped] = $this->checker->verifyQuotes($markdown, $sourceText);

        $this->assertSame(2, $stripped);
        $this->assertStringNotContainsString('first fabricated quote', $cleaned);
        $this->assertStringNotContainsString('second fabricated quote', $cleaned);
    }

    public function test_no_quotes_returns_unchanged(): void
    {
        $markdown = '## News\n\nThis article has no quoted spans at all. Just plain text.';
        [$cleaned, $stripped] = $this->checker->verifyQuotes($markdown, 'some source text');

        $this->assertSame($markdown, $cleaned);
        $this->assertSame(0, $stripped);
    }

    public function test_quote_grounded_via_high_similarity_not_stripped(): void
    {
        // Source has slightly different punctuation/wording — similarity should catch it.
        $sourceText = 'The CEO announced: we will invest dollar 50 million in the new facility next year';

        $markdown = '## Investment\n\n"we will invest $50 million in the new facility next year" the CEO announced.';

        [$cleaned, $stripped] = $this->checker->verifyQuotes($markdown, $sourceText);

        // Should survive via similarity fallback even if exact substring fails.
        $this->assertStringContainsString('invest', $cleaned);
        $this->assertSame(0, $stripped);
    }

    public function test_extract_quoted_spans_finds_double_quoted_text(): void
    {
        $markdown = 'He said "this is a real quote from sources" and then "another quote here too".';

        $spans = $this->checker->extractQuotedSpans($markdown);

        $this->assertContains('this is a real quote from sources', $spans);
        $this->assertContains('another quote here too', $spans);
    }

    public function test_extract_quoted_spans_ignores_short_quotes(): void
    {
        // A substantive quote (>=10 chars) is captured.
        $markdown = 'The minister said "this is long enough to be captured here" in his speech.';

        $spans = $this->checker->extractQuotedSpans($markdown);

        $this->assertContains('this is long enough to be captured here', $spans);
        // No short spans leak through.
        foreach ($spans as $span) {
            $this->assertGreaterThanOrEqual(10, strlen($span));
        }
    }
}
