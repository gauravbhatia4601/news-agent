<?php

namespace App\News\Sources;

use App\News\Sources\Contracts\NewsSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleNewsRssSource implements NewsSource
{
    /**
     * @var array<int, string>
     */
    private array $blockedSourceHosts = [
        'news.google.com',
        'google.com',
        'www.google.com',
        'googleusercontent.com',
    ];

    public const HIT_CACHE_KEY = 'news-engine:source-hits:google_rss';

    public function __construct(private readonly array $config = [])
    {
    }

    public function name(): string
    {
        return 'google_rss';
    }

    public function fetch(string $category, Carbon $freshThreshold, int $perCategoryFetchLimit, string $scope = 'india'): array
    {
        Cache::increment(self::HIT_CACHE_KEY);

        $baseFeedUrl = (string) ($this->config['base_feed_url'] ?? 'https://news.google.com/rss/search');
        $queryMap = $this->config['queries'] ?? [];
        $globalQueryMap = $scope === 'global' ? config('news-engine-global.sources.google_rss.queries', []) : [];
        $query = urlencode(($globalQueryMap[$category] ?? $queryMap[$category] ?? $category).' when:1d');

        $isGlobal = $scope === 'global';
        $hl = (string) ($this->config['hl'] ?? 'en-US');
        $gl = (string) ($this->config['gl'] ?? 'US');
        $ceid = (string) ($this->config['ceid'] ?? 'US:en');

        if (! $isGlobal) {
            $hl = (string) ($this->config['india_hl'] ?? 'en-IN');
            $gl = (string) ($this->config['india_gl'] ?? 'IN');
            $ceid = (string) ($this->config['india_ceid'] ?? 'IN:en');
        }

        $timeout = (int) ($this->config['timeout'] ?? 20);

        $url = "{$baseFeedUrl}?q={$query}&hl={$hl}&gl={$gl}&ceid={$ceid}";

        $response = Http::timeout($timeout)->get($url);
        $response->throw();

        $xml = @simplexml_load_string($response->body());
        if ($xml === false || ! isset($xml->channel->item)) {
            return [];
        }

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

        foreach ($xml->channel->item as $item) {
            if (count($items) >= $perCategoryFetchLimit) {
                break;
            }

            $headline = trim((string) $item->title);
            $sourceUrl = trim((string) $item->link);
            $rawDescription = (string) $item->description;
            $summary = trim(strip_tags($rawDescription));
            $publishedAtRaw = trim((string) $item->pubDate);
            $sourceName = parse_url($sourceUrl, PHP_URL_HOST) ?: 'N/A';

            if ($sourceUrl !== '') {
                $sourceUrl = preg_split('/\s+/', $sourceUrl)[0] ?? $sourceUrl;
            }

            if (Str::contains($headline, ' - ')) {
                $parts = explode(' - ', $headline);
                $sourceName = trim((string) end($parts)) ?: $sourceName;
            }

            if ($headline === '' || $sourceUrl === '') {
                continue;
            }

            $sourceUrl = $this->resolvePublisherUrl($sourceUrl, $rawDescription);

            $publishedAt = null;
            if ($publishedAtRaw !== '') {
                try {
                    $publishedAt = Carbon::parse($publishedAtRaw);
                } catch (\Throwable) {
                    $publishedAt = null;
                }
            }

            if ($publishedAt instanceof Carbon && $publishedAt->lt($freshThreshold)) {
                continue;
            }

            $canonicalUrl = preg_replace('/\?.*$/', '', $sourceUrl);
            $signature = sha1(Str::lower($headline).'|'.$canonicalUrl);

            $items[] = [
                'headline' => $headline,
                'summary' => $summary,
                'source_name' => $sourceName,
                'source_url' => $sourceUrl,
                'published_at' => $publishedAt,
                'signature' => $signature,
                'tokens' => $tokenize($headline),
            ];
        }

        usort($items, fn ($a, $b) => ($b['published_at']?->timestamp ?? 0) <=> ($a['published_at']?->timestamp ?? 0));

        return $items;
    }

    private function resolvePublisherUrl(string $sourceUrl, string $rawDescription): string
    {
        $sourceUrl = trim($sourceUrl);
        if ($sourceUrl === '') {
            return $sourceUrl;
        }

        if (! $this->isBlockedHost($sourceUrl)) {
            return $sourceUrl;
        }

        $candidates = [];

        $queryUrl = $this->extractEmbeddedQueryUrl($sourceUrl);
        if ($queryUrl !== null) {
            $candidates[] = $queryUrl;
        }

        $descriptionLinks = [];
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $rawDescription, $matches)) {
            $descriptionLinks = $matches[1] ?? [];
        }

        foreach ($descriptionLinks as $link) {
            $clean = html_entity_decode(trim((string) $link), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($clean !== '') {
                $candidates[] = $clean;
            }

            $embedded = $this->extractEmbeddedQueryUrl($clean);
            if ($embedded !== null) {
                $candidates[] = $embedded;
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeUrl((string) $candidate);
            if ($normalized === null || $this->isBlockedHost($normalized)) {
                continue;
            }

            return $normalized;
        }

        return $sourceUrl;
    }

    private function extractEmbeddedQueryUrl(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        foreach (['url', 'u', 'q'] as $key) {
            $value = trim((string) ($params[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $normalized = $this->normalizeUrl($value);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return null;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return null;
        }

        return preg_replace('/\s+/', '', $url) ?: null;
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
