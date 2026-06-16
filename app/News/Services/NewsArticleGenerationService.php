<?php

namespace App\News\Services;

use App\Ai\Agents\NewsArticleAgent;
use App\Ai\Agents\PlainTextNewsArticleAgent;
use App\Ai\Services\EntityExtractionService;
use App\News\Repositories\NewsTopicRepository;
use Illuminate\Support\Str;

class NewsArticleGenerationService
{
    private const MIN_ARTICLE_WORDS = 600;
    private const MIN_SECTION_COUNT = 4;
    private const MIN_ACTIVE_VOICE_RATIO = 0.55;
    private const MIN_SOURCE_COUNT = 3;

    public function __construct(
        private readonly NewsTopicRepository $repository,
        private readonly NewsArticleImageService $imageService,
        private readonly EntityExtractionService $entityExtractor,
    ) {}

    /**
     * @param  string[]  $topicSignatures
     * @return array{generated: int, failed: int}
     */
    public function generateForDiscoveredTopics(array $topicSignatures): array
    {
        $provider = (string) config('news-engine.generation.provider');
        $model = (string) config('news-engine.generation.model');
        $timeout = (int) config('news-engine.generation.timeout', 300);

        $generated = 0;
        $failed = 0;
        $topics = $this->repository->getPendingTopicsBySignatures($topicSignatures);

        $isOllamaCloud = str_starts_with($provider, 'ollama');
        $agent = $isOllamaCloud ? new PlainTextNewsArticleAgent() : new NewsArticleAgent();

        foreach ($topics as $topic) {
            $sourceRows = array_map(function (array $source): array {
                return [
                    'source_name' => $source['source_name'] ?? 'N/A',
                    'source_url' => $source['source_url'] ?? '',
                    'headline' => $source['headline'] ?? '',
                    'summary' => $source['summary'] ?? '',
                    'published_at' => $source['published_at'] ?? null,
                ];
            }, $topic['sources']);

            $entities = $this->entityExtractor->extract($sourceRows);
            $entityContext = $entities->toPromptContext();

                $payload = json_encode([
                    'entities_context' => "Extracted from sources:\n" . $entityContext,
                    'writing_goal' => 'Write a professional, authoritative news article in the style of The Economist and The Hindu for an educated Indian audience. Use journalistic techniques: lead with a hook, name actors, use active voice, be concrete with data and dates, show consequence. Weave SEO keywords naturally throughout — never stuff or list them. Answer the question a searching reader came for in the first two paragraphs.',
                    'format_requirements' => [
                        'Use markdown headings with ## for section titles',
                        'At least 4 distinct sections plus a FAQ section',
                        'Each section: 2-4 substantive paragraphs',
                        'Target 700-1000 words',
                        'Write as original journalism — synthesize facts from all sources into your own authoritative voice. Never use [1], [2] citation markers in the article body. The reader should feel they are reading a single expert journalist, not a compilation of sources.',
                        'Include specific data points, dates, and numbers wherever source material supports it',
                        'First 100 words must contain the primary topic keyword',
                        'Primary keyword must appear in at least one H2 heading and the closing paragraph',
                        'At least one direct quote or properly paraphrased statement from the source material',
                        'End each section with forward motion — what happens next, not a restatement',
                    ],
                    'topic' => $topic['topic_name'],
                    'category' => $topic['category'],
                    'location' => $topic['location'] ?? null,
                    'sources' => $sourceRows,
                    'entity_context' => $entityContext,
                    'primary_keyword' => $entities->topicTerm ?: $topic['topic_name'],
                    'topical_keywords' => implode(', ', $entities->primaryTopics),
                    'search_questions' => $entities->searchQuestions,
                    'seo_requirements' => [
                        'Meta title: 50-60 chars, front-load primary keyword, include number/year if relevant, make it clickable and intriguing',
                        'Meta description: 140-155 chars with primary keyword, tell the reader exactly what value to expect',
                        'Meta keywords: 10-15 as array. Include: primary topic keyword, 3-4 secondary keywords, 2-3 location keywords, 3-4 long-tail question variations, 1-2 broad category terms',
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $generationStart = microtime(true);
                $response = $agent->prompt($payload, [], $provider, $model, $timeout);

                if ($isOllamaCloud) {
                    $response = $this->parseJsonResponse($response);
                }

                $title = ! empty($response['title']) ? trim((string) $response['title']) : (string) $topic['topic_name'];
                $rawArticle = ! empty($response['article']) ? trim((string) $response['article']) : '';
                $author = ! empty($response['author']) ? trim((string) $response['author']) : 'AI News Desk';
                $articleMarkdown = $this->sanitizeArticleMarkdown($rawArticle);
                $article = Str::markdown($articleMarkdown, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);
                $article = $this->ensureStructuredHtml($article, $articleMarkdown);

                $readTimeMinutes = ! empty($response['read_time_minutes'])
                    ? (int) $response['read_time_minutes']
                    : max(1, (int) ceil(str_word_count(strip_tags($article)) / 220));

                $citations = ! empty($response['citations']) && is_array($response['citations'])
                    ? $response['citations']
                    : [];

                $faqSection = ! empty($response['faq_section']) && is_array($response['faq_section'])
                    ? $response['faq_section']
                    : [];

                $internalLinks = ! empty($response['internal_links']) && is_array($response['internal_links'])
                    ? array_values(array_filter(array_map('trim', $response['internal_links']), fn ($s) => $s !== ''))
                    : [];

                $metaTitle = ! empty($response['meta_title']) ? trim((string) $response['meta_title']) : $title;
                $metaDescription = ! empty($response['meta_description'])
                    ? trim((string) $response['meta_description'])
                    : Str::limit(strip_tags($article), 160);
                $metaKeywords = ! empty($response['meta_keywords']) && is_array($response['meta_keywords'])
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

                [$status, $qualityReport] = $this->assessQuality(
                    $articleMarkdown,
                    $article,
                    count($sourceRows),
                    $metaKeywords,
                    $faqSection,
                );

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
                        'faq_section' => $faqSection,
                        'internal_links' => $internalLinks,
                        'entities' => [
                            'people' => $entities->people,
                            'organizations' => $entities->organizations,
                            'locations' => $entities->locations,
                            'primary_topic_term' => $entities->topicTerm,
                        ],
                        'image_origin' => $resolvedImage['image_origin'] ?? null,
                    ],
                    status: $status,
                    qualityReport: $qualityReport,
                    generationDurationSeconds: (int) round(microtime(true) - $generationStart),
                );

                $generated++;
        }

        return [
            'generated' => $generated,
            'failed' => $failed,
        ];
    }

    private function assessQuality(string $markdown, string $html, int $sourceCount, string $metaKeywords, array $faqSection): array
    {
        $issues = [];
        $plainText = strip_tags($html);
        $wordCount = str_word_count($plainText);
        $sectionCount = $this->countSections($markdown);
        $keywordCount = count(array_filter(explode(', ', $metaKeywords)));
        $activeVoiceRatio = $this->estimateActiveVoiceRatio($plainText);
        $hasHook = $this->hasCompellingHook($plainText);
        $faqCount = count($faqSection);

        if ($wordCount < self::MIN_ARTICLE_WORDS) {
            $issues[] = "Word count {$wordCount} below minimum " . self::MIN_ARTICLE_WORDS;
        }
        if ($sectionCount < self::MIN_SECTION_COUNT) {
            $issues[] = "Section count {$sectionCount} below minimum " . self::MIN_SECTION_COUNT;
        }
        if ($sourceCount < self::MIN_SOURCE_COUNT) {
            $issues[] = "Only {$sourceCount} sources — need at least " . self::MIN_SOURCE_COUNT . " for multi-source verification";
        }
        if ($keywordCount < 8) {
            $issues[] = "Only {$keywordCount} SEO keywords — aim for 10-15";
        }
        if ($activeVoiceRatio < self::MIN_ACTIVE_VOICE_RATIO) {
            $issues[] = sprintf("Active voice ratio %.0f%% below target %.0f%%", $activeVoiceRatio * 100, self::MIN_ACTIVE_VOICE_RATIO * 100);
        }
        if (! $hasHook) {
            $issues[] = "Opening paragraph lacks a compelling hook — consider a specific scene, statistic, quote, or consequence";
        }
        if ($faqCount < 2) {
            $issues[] = "Only {$faqCount} FAQ items — aim for at least 3 for featured snippet opportunities";
        }

        $qualityReport = $issues ? json_encode(['issues' => $issues, 'metrics' => [
            'word_count' => $wordCount,
            'section_count' => $sectionCount,
            'keyword_count' => $keywordCount,
            'active_voice_ratio' => round($activeVoiceRatio, 2),
            'source_count' => $sourceCount,
            'has_hook' => $hasHook,
            'faq_count' => $faqCount,
        ]]) : null;

        $status = ($wordCount >= self::MIN_ARTICLE_WORDS && $sectionCount >= self::MIN_SECTION_COUNT && count($issues) <= 3)
            ? 'published'
            : 'draft';

        return [$status, $qualityReport];
    }

    private function estimateActiveVoiceRatio(string $plainText): float
    {
        $sentences = preg_split('/[.!?]+/', $plainText) ?: [];
        $sentences = array_filter($sentences, fn ($s) => str_word_count(trim($s)) >= 4);

        if (count($sentences) === 0) {
            return 0.0;
        }

        $passiveIndicators = ['is being', 'are being', 'was being', 'were being', 'has been', 'have been', 'had been', 'will be', 'would be', 'could be', 'should be', 'may be', 'might be', 'is expected', 'are expected', 'is reported', 'are reported', 'it was', 'it is'];

        $passiveCount = 0;
        foreach ($sentences as $sentence) {
            $lower = mb_strtolower(trim($sentence));
            foreach ($passiveIndicators as $indicator) {
                if (str_contains($lower, $indicator)) {
                    $passiveCount++;
                    continue 2;
                }
            }
        }

        $activeCount = count($sentences) - $passiveCount;

        return count($sentences) > 0 ? $activeCount / count($sentences) : 0.0;
    }

    private function hasCompellingHook(string $plainText): bool
    {
        $firstTwoSentences = implode(' ', array_slice(array_filter(preg_split('/[.!?]+/', $plainText) ?: [], fn ($s) => trim($s) !== ''), 0, 2));

        if (mb_strlen($firstTwoSentences) < 30) {
            return false;
        }

        $boringOpeners = ['in recent', 'according to', 'it has been', 'there has been', 'recently', 'today', 'yesterday', 'last week', 'this week', 'it was announced', 'officials said', 'reports suggest', 'sources say', 'as per', 'news has'];

        $firstSentence = mb_strtolower(trim((array_filter(preg_split('/[.!?]+/', $plainText) ?: [], fn ($s) => trim($s) !== ''))[0] ?? ''));

        foreach ($boringOpeners as $boring) {
            if (str_starts_with($firstSentence, $boring)) {
                return false;
            }
        }

        $hasSpecific = preg_match('/\d{1,2}[,\s]+\d{4}|\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\b|₹\d|Rs\.?\s*\d|\b\d+\s*(?:crore|lakh|million|billion|percent|%)\b|\b(?:said|stated|told|announced)\b/i', $firstTwoSentences);

        return (bool) $hasSpecific;
    }

    private function parseJsonResponse(mixed $raw): array
    {
        $text = is_string($raw) ? $raw : (string) $raw;

        $text = preg_replace('/^```json\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = preg_replace('/^```\s*/', '', $text) ?? $text;

        $text = trim($text);

        $start = strpos($text, '{');
        if ($start === false) {
            throw new \RuntimeException('No JSON object found in model response. Raw: ' . substr($text, 0, 500));
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $end = null;

        for ($i = $start; $i < strlen($text); $i++) {
            $ch = $text[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        if ($end === null) {
            throw new \RuntimeException('Unterminated JSON object in model response. Raw: ' . substr($text, 0, 500));
        }

        $json = substr($text, $start, $end - $start + 1);

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            $fixed = $this->repairJson($json);
            $decoded = json_decode($fixed, true);
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('Failed to decode JSON from model response. Raw: ' . substr($json, 0, 500));
        }

        return $decoded;
    }

    private function repairJson(string $json): string
    {
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;

        return $json;
    }

    private function sanitizeArticleMarkdown(string $text): string
    {
        $clean = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = trim($clean, " \t\n\r\0\x0B\"'");
        $clean = preg_replace('/```[\s\S]*?```/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\r\n?/', "\n", $clean) ?? $clean;
        $clean = preg_replace('/\\\\n/u', "\n", $clean) ?? $clean;
        $clean = preg_replace('/\\\\t/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/[^\P{C}\n\t]/u', '', $clean) ?? $clean;
        $clean = preg_replace('/(^|[^\n])\s*(#{2,6})([^\s#])/m', "$1\n\n$2 $3", $clean) ?? $clean;
        $clean = preg_replace('/\s+(#{2,6}\s)/u', "\n\n$1", $clean) ?? $clean;
        $clean = preg_replace('/\n(#{2,6}\s[^\n]+)\s+(?=#{2,6}\s)/u', "\n$1\n", $clean) ?? $clean;
        $clean = preg_replace('/^[\*\-]\s+/m', '', $clean) ?? $clean;
        $clean = preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean;
        $clean = str_replace(['&nbsp;', "\u{00A0}"], ' ', $clean);
        $clean = preg_replace('/[ \t]{2,}/', ' ', $clean) ?? $clean;
        $clean = preg_replace('/[~`]{2,}/', '', $clean) ?? $clean;
        $clean = preg_replace('/(?:^|\s)#{7,}(?=\s|$)/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\[(?:source|citation)\]/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\[\d+\]/', '', $clean) ?? $clean;

        return trim($clean);
    }

    private function countSections(string $markdown): int
    {
        preg_match_all('/^#{2,6}\s+/m', $markdown, $matches);

        return count($matches[0]);
    }

    private function ensureStructuredHtml(string $html, string $markdown): string
    {
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

            return '<p>' . e($text) . '</p>';
        }, $paragraphs)));

        if ($safeParagraphs !== []) {
            return implode("\n", $safeParagraphs);
        }

        return '<p>' . e(trim($markdown)) . '</p>';
    }
}
