<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Ai\Services\EntityExtractionService;
use App\News\Repositories\NewsTopicRepository;
use App\News\Services\NewsArticleGenerationService;
use App\News\Services\NewsArticleImageService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Verifies the title/meta_description clamping that prevents
 * "Title too long" and "Meta description too long" SEO issues.
 * Uses Reflection to access the private clampTitle method — no DB, no LLM.
 */
class SeoClampTest extends TestCase
{
    public function test_short_title_passes_through_unchanged(): void
    {
        $service = $this->instantiateService();
        $method = new ReflectionMethod($service, 'clampTitle');

        $this->assertSame('India GDP Growth Hits 6.5%', $method->invoke($service, 'India GDP Growth Hits 6.5%', 60));
    }

    public function test_long_title_clamped_to_60_at_word_boundary(): void
    {
        $service = $this->instantiateService();
        $method = new ReflectionMethod($service, 'clampTitle');

        $longTitle = 'India GDP Growth Surges to 6.5 Percent in Q3 2024 Beating Economist Estimates and Market Predictions';
        $result = $method->invoke($service, $longTitle, 60);

        // Result must be ≤60 chars (mb_strlen) + 1 ellipsis char
        $this->assertLessThanOrEqual(61, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
        // Must not contain words from beyond the cut point
        $this->assertStringNotContainsString('Economist', $result);
        $this->assertStringNotContainsString('Predictions', $result);
    }

    public function test_title_exactly_60_chars_unchanged(): void
    {
        $service = $this->instantiateService();
        $method = new ReflectionMethod($service, 'clampTitle');

        $exact = str_repeat('a', 60);
        $this->assertSame($exact, $method->invoke($service, $exact, 60));
    }

    public function test_meta_description_clamped_to_155(): void
    {
        $service = $this->instantiateService();
        $method = new ReflectionMethod($service, 'clampTitle');

        $longDesc = str_repeat('The quick brown fox jumps. ', 10);
        $result = $method->invoke($service, $longDesc, 155);

        $this->assertLessThanOrEqual(156, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function test_empty_title_returns_empty(): void
    {
        $service = $this->instantiateService();
        $method = new ReflectionMethod($service, 'clampTitle');

        $this->assertSame('', $method->invoke($service, '', 60));
    }

    public function test_title_with_no_spaces_before_cutoff_hard_cut(): void
    {
        $service = $this->instantiateService();
        $method = new ReflectionMethod($service, 'clampTitle');

        // Single long word — no space to backtrack to
        $longWord = str_repeat('x', 80);
        $result = $method->invoke($service, $longWord, 60);

        // mb_substr(text, 0, 59) + ellipsis = 60 mb chars
        $this->assertSame(60, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    private function instantiateService(): NewsArticleGenerationService
    {
        $repository = $this->app->make(NewsTopicRepository::class);
        $imageService = $this->app->make(NewsArticleImageService::class);
        $entityExtractor = $this->app->make(EntityExtractionService::class);

        return new NewsArticleGenerationService($repository, $imageService, $entityExtractor);
    }
}
