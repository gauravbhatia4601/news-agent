<?php

namespace Tests\Unit;

use App\Ai\Services\EntityExtractionService;
use App\News\Repositories\NewsTopicRepository;
use App\News\Services\NewsArticleGenerationService;
use App\News\Services\NewsArticleImageService;
use ReflectionMethod;
use Tests\TestCase;

class QualityGateTest extends TestCase
{
    /**
     * A well-structured article (>=400 words, >=3 sections, good stats) should publish.
     */
    public function test_assess_quality_publishes_good_article(): void
    {
        $service = $this->instantiateService();
        $method = new ReflectionMethod($service, 'assessQuality');

        $markdown = $this->buildMarkdown(words: 500, sections: 4);
        $html = '<h2>Section One</h2><p>'.str_repeat('The quick brown fox jumps over the lazy dog. ', 25).'</p>'
            .'<h2>Section Two</h2><p>'.str_repeat('Active voice testing remains important here. ', 25).'</p>'
            .'<h2>Section Three</h2><p>'.str_repeat('Specific data shows 50 percent growth in 2024. ', 25).'</p>'
            .'<h2>Section Four</h2><p>'.str_repeat('The committee said the report confirms findings. ', 25).'</p>';

        $keywords = implode(', ', array_map(fn ($i) => "keyword{$i}", range(1, 12)));
        $faqSection = [
            ['question' => 'Q1?', 'answer' => 'A1'],
            ['question' => 'Q2?', 'answer' => 'A2'],
            ['question' => 'Q3?', 'answer' => 'A3'],
        ];

        [$status, $report] = $method->invoke($service, $markdown, $html, 3, $keywords, $faqSection);

        $this->assertSame('published', $status);
    }

    /**
     * A short or section-poor article should be drafted.
     */
    public function test_assess_quality_drafts_short_article(): void
    {
        $service = $this->instantiateService();
        $method = new ReflectionMethod($service, 'assessQuality');

        $markdown = "## Section One\n\nShort content here.\n\n## Section Two\n\nAlso short.\n";
        $html = '<h2>Section One</h2><p>Short content here.</p><h2>Section Two</p><p>Also short.</p>';

        [$status, $report] = $method->invoke($service, $markdown, $html, 1, '', []);

        $this->assertSame('draft', $status);
    }

    private function instantiateService(): NewsArticleGenerationService
    {
        $repository = $this->app->make(NewsTopicRepository::class);
        $imageService = $this->app->make(NewsArticleImageService::class);
        $entityExtractor = $this->app->make(EntityExtractionService::class);

        return new NewsArticleGenerationService($repository, $imageService, $entityExtractor);
    }

    private function buildMarkdown(int $words, int $sections): string
    {
        $markdown = '';
        $wordsPerSection = (int) ceil($words / $sections);
        for ($i = 1; $i <= $sections; $i++) {
            $markdown .= "## Section {$i}\n\n";
            $markdown .= str_repeat('The active voice sentence with data 2024 shows growth. ', max(1, (int) ceil($wordsPerSection / 10)));
            $markdown .= "\n\n";
        }

        return $markdown;
    }
}
