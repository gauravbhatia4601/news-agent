<?php

namespace App\News\Services;

use App\Ai\Agents\NewsArticleAgent;
use App\News\Repositories\NewsTopicRepository;
use Illuminate\Support\Str;

class NewsArticleGenerationService
{
    private const MIN_ARTICLE_WORDS = 550;

    private const MIN_SECTION_COUNT = 4;

    public function __construct(
        private readonly NewsTopicRepository $repository,
        private readonly NewsArticleImageService $imageService,
    )
    {
    }

    /**
     * @param  string[]  $topicSignatures
     * @return array{generated: int, failed: int}
     */
    public function generateForDiscoveredTopics(array $topicSignatures): array
    {
        $provider = (string) config('news-engine.generation.provider');
        $model = (string) config('news-engine.generation.model');
        $timeout = (int) config('news-engine.generation.timeout', 120);

        $generated = 0;
        $failed = 0;
        $topics = $this->repository->getPendingTopicsBySignatures($topicSignatures);
        
        $agent = new NewsArticleAgent();

        foreach ($topics as $topic) {
            try {
                $sourceRows = array_map(function (array $source): array {
                    return [
                        'source_name' => $source['source_name'] ?? 'N/A',
                        'source_url' => $source['source_url'] ?? '',
                        'headline' => $source['headline'] ?? '',
                        'summary' => $source['summary'] ?? '',
                        'published_at' => $source['published_at'] ?? null,
                    ];
                }, $topic['sources']);

                $payload = json_encode([
                    'writing_goal' => 'Write a professional, long-form, neutral news article with multiple sections and clear paragraph breaks.',
                    'format_requirements' => [
                        'Use markdown headings with ## for section titles',
                        'At least 4 sections',
                        'Each section should include 1-3 short paragraphs',
                        'Target 700-1100 words',
                        'Use inline citations like [1], [2]',
                    ],
                    'topic' => $topic['topic_name'],
                    'category' => $topic['category'],
                    'sources' => $sourceRows,
                ], JSON_PRETTY_PRINT);

                $response = $agent->prompt($payload, [], $provider, $model, $timeout);

                $title = !empty($response['title']) ? trim((string) $response['title']) : (string) $topic['topic_name'];
                $rawArticle = !empty($response['article']) ? trim((string) $response['article']) : '';
                $author = !empty($response['author']) ? trim((string) $response['author']) : 'AI News Desk';
                $articleMarkdown = $this->sanitizeArticleMarkdown($rawArticle);
                $article = Str::markdown($articleMarkdown, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);
                $article = $this->ensureStructuredHtml($article, $articleMarkdown);
                
                $readTimeMinutes = !empty($response['read_time_minutes'])
                    ? (int) $response['read_time_minutes']
                    : max(1, (int) ceil(str_word_count(strip_tags($article)) / 220));

                $citations = !empty($response['citations']) && is_array($response['citations'])
                    ? $response['citations']
                    : [];
                $metaTitle = !empty($response['meta_title']) ? trim((string) $response['meta_title']) : $title;
                $metaDescription = !empty($response['meta_description'])
                    ? trim((string) $response['meta_description'])
                    : Str::limit(strip_tags($article), 160);
                $metaKeywords = !empty($response['meta_keywords']) && is_array($response['meta_keywords'])
                    ? implode(', ', array_map(static fn ($v) => trim((string) $v), $response['meta_keywords']))
                    : '';

                if ($article === '') {
                    throw new \RuntimeException('Article generation returned empty content.');
                }
                if (str_word_count(strip_tags($article)) < self::MIN_ARTICLE_WORDS) {
                    throw new \RuntimeException('Article generation returned too little content.');
                }
                if ($this->countSections($articleMarkdown) < self::MIN_SECTION_COUNT) {
                    throw new \RuntimeException('Article generation returned insufficient sections.');
                }

                $resolvedImage = $this->imageService->resolveImageForTopic($topic, $title);

                $this->repository->saveGeneratedArticle(
                    (int) $topic['id'],
                    title: Str::limit($title, 250, ''),
                    content: $article,
                    provider: $provider,
                    model: $model,
                    metaTitle: Str::limit($metaTitle, 255, ''),
                    metaDescription: $metaDescription,
                    metaKeywords: $metaKeywords,
                    imageUrl: $resolvedImage['image_url'] ?? null,
                    thumbnailUrl: $resolvedImage['thumbnail_url'] ?? null,
                    metadata: [
                        'source_count' => count($sourceRows),
                        'author' => $author,
                        'read_time_minutes' => $readTimeMinutes,
                        'citations' => $citations,
                        'image_origin' => $resolvedImage['image_origin'] ?? null,
                    ],
                );

                $generated++;
            } catch (\Throwable $e) {
                $this->repository->markGenerationFailed((int) $topic['id']);
                \Log::error('News article generation failed: ' . $e->getMessage());
                $failed++;
            }
        }

        return [
            'generated' => $generated,
            'failed' => $failed,
        ];
    }

    private function sanitizeArticleMarkdown(string $text): string
    {
        $clean = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = trim($clean, " \t\n\r\0\x0B\"'");
        $clean = preg_replace('/```[\s\S]*?```/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\r\n?/', "\n", $clean) ?? $clean;
        // Decode common escaped control sequences sometimes returned inside JSON string fields.
        $clean = preg_replace('/\\\\n/u', "\n", $clean) ?? $clean;
        $clean = preg_replace('/\\\\t/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/[^\P{C}\n\t]/u', '', $clean) ?? $clean;
        // Ensure inline headings don't collapse into one giant paragraph and normalize malformed headings.
        $clean = preg_replace('/(^|[^\n])\s*(#{2,6})([^\s#])/m', "$1\n\n$2 $3", $clean) ?? $clean;
        $clean = preg_replace('/\s+(#{2,6}\s)/u', "\n\n$1", $clean) ?? $clean;
        $clean = preg_replace('/\n(#{2,6}\s[^\n]+)\s+(?=#{2,6}\s)/u', "\n$1\n", $clean) ?? $clean;
        // Convert standalone bullet-like noise to sentences.
        $clean = preg_replace('/^[\*\-]\s+/m', '', $clean) ?? $clean;
        $clean = preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean;
        // Drop visual noise while preserving citations like [1].
        $clean = str_replace(['&nbsp;', "\u{00A0}"], ' ', $clean);
        $clean = preg_replace('/[ \t]{2,}/', ' ', $clean) ?? $clean;
        $clean = preg_replace('/[~`]{2,}/', '', $clean) ?? $clean;
        $clean = preg_replace('/(?:^|\s)#{7,}(?=\s|$)/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\[(?:source|citation)\]/iu', '', $clean) ?? $clean;

        return trim($clean);
    }

    private function countSections(string $markdown): int
    {
        preg_match_all('/^#{2,6}\s+/m', $markdown, $matches);

        return count($matches[0]);
    }

    private function ensureStructuredHtml(string $html, string $markdown): string
    {
        // If markdown conversion did not produce structural tags, fallback to safe paragraph rendering.
        $hasStructure = str_contains($html, '<h2>')
            || str_contains($html, '<h3>')
            || str_contains($html, '<p>');

        if ($hasStructure) {
            return trim($html);
        }

        $paragraphs = preg_split('/\n{2,}/', trim($markdown)) ?: [];
        $safeParagraphs = array_values(array_filter(array_map(static function (string $paragraph): string {
            $text = trim($paragraph);
            if ($text === '') {
                return '';
            }

            return '<p>'.e($text).'</p>';
        }, $paragraphs)));

        if ($safeParagraphs !== []) {
            return implode("\n", $safeParagraphs);
        }

        return '<p>'.e(trim($markdown)).'</p>';
    }
}
