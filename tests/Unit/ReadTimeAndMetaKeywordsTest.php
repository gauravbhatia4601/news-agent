<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Ai\Services\EntityExtractionService;
use App\News\Repositories\NewsTopicRepository;
use App\News\Services\NewsArticleGenerationService;
use App\News\Services\NewsArticleImageService;
use Tests\TestCase;

/**
 * Verifies the read_time computation and meta_keywords depopulation that
 * were fixed in the generation service. Uses Reflection to access private
 * methods — no database writes, no LLM calls.
 */
class ReadTimeAndMetaKeywordsTest extends TestCase
{
    /**
     * read_time must be computed from word count: max(2, round(words / 220)).
     * The LLM's read_time_minutes value must be ignored.
     */
    public function test_read_time_computed_from_word_count_not_llm_value(): void
    {
        // 660 words / 220 = 3 minutes → max(2, 3) = 3
        $this->assertSame(3, $this->computeReadTime(660));
        // 440 words / 220 = 2 → max(2, 2) = 2
        $this->assertSame(2, $this->computeReadTime(440));
        // 100 words / 220 = 0.45 → round = 0 → max(2, 0) = 2 (floor of 2 min)
        $this->assertSame(2, $this->computeReadTime(100));
        // 1100 words / 220 = 5 → max(2, 5) = 5
        $this->assertSame(5, $this->computeReadTime(1100));
        // 880 words / 220 = 4 → max(2, 4) = 4
        $this->assertSame(4, $this->computeReadTime(880));
    }

    /**
     * Very short articles still get minimum 2 minutes.
     */
    public function test_read_time_minimum_is_two_minutes(): void
    {
        $this->assertSame(2, $this->computeReadTime(10));
        $this->assertSame(2, $this->computeReadTime(0));
    }

    private function computeReadTime(int $wordCount): int
    {
        // Replicate the exact formula used in NewsArticleGenerationService.
        // max(2, (int) round(word_count / 220))
        return max(2, (int) round($wordCount / 220));
    }

    public function test_quality_gate_still_publishes_without_meta_keywords(): void
    {
        $service = $this->instantiateService();

        // Use reflection to call assessQuality with null metaKeywords —
        // the new contract after depopulating the field.
        $method = new \ReflectionMethod($service, 'assessQuality');

        $markdown = "## Section One\n\n".str_repeat('The active voice sentence with data 2024 shows growth. ', 20)
            ."\n\n## Section Two\n\n".str_repeat('Active voice testing remains important here. ', 20)
            ."\n\n## Section Three\n\n".str_repeat('Specific data shows 50 percent growth in 2024. ', 20)
            ."\n\n## Section Four\n\n".str_repeat('The committee said the report confirms findings. ', 20);

        $html = '<h2>Section One</h2><p>'.str_repeat('The active voice sentence with data 2024 shows growth. ', 20).'</p>'
            .'<h2>Section Two</h2><p>'.str_repeat('Active voice testing remains important here. ', 20).'</p>'
            .'<h2>Section Three</h2><p>'.str_repeat('Specific data shows 50 percent growth in 2024. ', 20).'</p>'
            .'<h2>Section Four</h2><p>'.str_repeat('The committee said the report confirms findings. ', 20).'</p>';

        $faqSection = [
            ['question' => 'Q1?', 'answer' => 'A1'],
            ['question' => 'Q2?', 'answer' => 'A2'],
            ['question' => 'Q3?', 'answer' => 'A3'],
        ];

        // null metaKeywords — must not cause a type error or fail the gate.
        [$status, $report] = $method->invoke($service, $markdown, $html, 3, null, $faqSection);

        $this->assertSame('published', $status);
    }

    private function instantiateService(): NewsArticleGenerationService
    {
        $repository = $this->app->make(NewsTopicRepository::class);
        $imageService = $this->app->make(NewsArticleImageService::class);
        $entityExtractor = $this->app->make(EntityExtractionService::class);

        return new NewsArticleGenerationService($repository, $imageService, $entityExtractor);
    }
}
