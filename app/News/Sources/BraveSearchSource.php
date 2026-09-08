<?php

namespace App\News\Sources;

use App\News\Sources\Contracts\NewsSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BraveSearchSource implements NewsSource
{
    /**
     * @var array<int, string>
     */
    private array $blockedSourceHosts = [
        'brave.com',
        'search.brave.com',
    ];

    public const HIT_CACHE_KEY = 'news-engine:source-hits:brave_search';

    public function __construct(private readonly array $config = []) {}

    public function name(): string
    {
        return 'brave_search';
    }

    public function fetch(string $category, Carbon $freshThreshold, int $perCategoryFetchLimit, string $scope = 'india'): array
    {
        Cache::increment(self::HIT_CACHE_KEY);

        $apiKey = (string) ($this->config['api_key'] ?? '');
        if ($apiKey === '') {
            throw new \RuntimeException('Brave Search API key is not configured.');
        }

        $baseUrl = (string) ($this->config['base_url'] ?? 'https://api.search.brave.com/res/v1');
        $timeout = (int) ($this->config['timeout'] ?? 20);
        $searchLang = (string) ($this->config['search_lang'] ?? 'en');
        $freshness = (string) ($this->config['freshness'] ?? 'pw');
        $maxUrls = (int) ($this->config['max_urls'] ?? 20);
        $maxTokens = (int) ($this->config['max_tokens'] ?? 8192);

        $queryMap = $this->config['queries'] ?? [];
        $globalQueryMap = $scope === 'global' ? config('news-engine-global.sources.brave_search.queries', []) : [];
        $query = $globalQueryMap[$category] ?? $queryMap[$category] ?? $category;

        $url = rtrim($baseUrl, '/').'/llm/context';

        $response = Http::timeout($timeout)
            ->withHeaders([
                'X-Subscription-Token' => $apiKey,
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip',
            ])
            ->get($url, [
                'q' => $query,
                'count' => min($perCategoryFetchLimit, 50),
                'search_lang' => $searchLang,
                'freshness' => $freshness,
                'enable_source_metadata' => true,
                'text_decorations' => false,
                'maximum_number_of_urls' => min($maxUrls, 50),
                'maximum_number_of_tokens' => min($maxTokens, 32768),
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException('Brave LLM Context API returned status '.$response->status());
        }

        $data = $response->json();
        $grounding = $data['grounding'] ?? [];
        $sources = $grounding['sources'] ?? [];

        $stopWords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'how', 'in', 'is', 'it', 'its',
            'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'why', 'with',
            'today', 'latest', 'live', 'update', 'updates', 'news',
        ];

        $tokenize = function (string $headline) use ($stopWords): array {
            $text = Str::of($headline)
                ->lower()
                ->replaceMatches('/[^a-z0-9\s]/', ' ')
                ->squish()
                ->value();

            $tokens = explode(' ', $text);

            return array_values(array_unique(array_filter($tokens, function ($token) use ($stopWords) {
                return $token !== '' && ! in_array($token, $stopWords, true) && strlen($token) > 2;
            })));
        };

        $items = [];

        foreach ($sources as $sourceUrl => $meta) {
            $sourceUrl = trim((string) $sourceUrl);
            $headline = trim((string) ($meta['title'] ?? ''));
            $description = trim((string) ($meta['description'] ?? ''));

            if ($headline === '' || $sourceUrl === '') {
                continue;
            }

            if ($this->isBlockedHost($sourceUrl)) {
                continue;
            }

            // Collect snippets for this URL from various grounding sections
            $snippets = [];
            foreach (['snippets', 'sections', 'highlights', 'content'] as $section) {
                if (! isset($grounding[$section]) || ! is_array($grounding[$section])) {
                    continue;
                }

                foreach ($grounding[$section] as $chunk) {
                    if (! is_array($chunk)) {
                        continue;
                    }

                    $chunkUrl = $chunk['url'] ?? $chunk['source'] ?? null;
                    if ($chunkUrl === $sourceUrl || $chunkUrl === $sourceUrl.'/' || rtrim($chunkUrl, '/') === rtrim($sourceUrl, '/')) {
                        $text = $chunk['text'] ?? $chunk['content'] ?? '';
                        if (is_string($text) && $text !== '') {
                            $snippets[] = $text;
                        }
                    }
                }
            }

            $summary = implode(' ', array_filter($snippets)) ?: $description;

            $canonicalUrl = preg_replace('/\?.*$/', '', $sourceUrl);
            $signature = sha1(Str::lower($headline).'|'.$canonicalUrl);

            $items[] = [
                'headline' => $headline,
                'summary' => $summary,
                'source_name' => parse_url($sourceUrl, PHP_URL_HOST) ?? 'N/A',
                'source_url' => $sourceUrl,
                'published_at' => now(), // LLM context is freshly retrieved
                'signature' => $signature,
                'tokens' => $tokenize($headline),
            ];
        }

        usort($items, fn ($a, $b) => ($b['published_at']?->timestamp ?? 0) <=> ($a['published_at']?->timestamp ?? 0));

        return $items;
    }

    private function isBlockedHost(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return true;
        }

        foreach ($this->blockedSourceHosts as $blocked) {
            $blocked = Str::lower($blocked);
            if ($host === $blocked || Str::endsWith($host, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }
}
