<?php

namespace App\News\Sources;

use App\News\Sources\Contracts\NewsSource;
use App\News\Sources\Support\HeadlineTokenizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * GDELT 2.0 DOC API source — free, no key.
 * https://api.gdeltproject.org/api/v2/doc/doc?query=...&mode=artlist&format=json&maxrecords=75&timespan={adaptive}
 */
class GdeltSource implements NewsSource
{
    public const HIT_CACHE_KEY = 'news-engine:source-hits:gdelt';

    public function __construct(private readonly array $config = []) {}

    public function name(): string
    {
        return 'gdelt';
    }

    public function fetch(
        string $category,
        Carbon $freshThreshold,
        int $perCategoryFetchLimit,
        string $scope = 'india',
        ?string $freshnessWindow = null,
        ?string $freshnessOverride = null,
    ): array {
        Cache::increment(self::HIT_CACHE_KEY);

        $baseUrl = (string) ($this->config['base_url'] ?? config('news-engine.live_stories.gdelt.base_url', 'https://api.gdeltproject.org/api/v2/doc/doc'));
        $timeout = (int) ($this->config['timeout'] ?? config('news-engine.live_stories.gdelt.timeout', 20));
        $maxrecords = (int) ($this->config['maxrecords'] ?? config('news-engine.live_stories.gdelt.maxrecords', 75));
        $timespan = $freshnessWindow ?? '24h';

        $url = $baseUrl.'?'.http_build_query([
            'query' => $category,
            'mode' => 'artlist',
            'format' => 'json',
            'maxrecords' => min($maxrecords, 250),
            'timespan' => $timespan,
        ]);

        $response = Http::timeout($timeout)->get($url);

        if (! $response->ok()) {
            throw new \RuntimeException('GDELT DOC API returned status '.$response->status());
        }

        $data = $response->json();
        $articles = $data['articles'] ?? [];

        if (! is_array($articles)) {
            return [];
        }

        $items = [];

        foreach ($articles as $article) {
            if (count($items) >= $perCategoryFetchLimit) {
                break;
            }

            $headline = trim((string) ($article['title'] ?? ''));
            $sourceUrl = trim((string) ($article['url'] ?? ''));

            if ($headline === '' || $sourceUrl === '') {
                continue;
            }

            $sourceName = trim((string) ($article['domain'] ?? '')) ?: (parse_url($sourceUrl, PHP_URL_HOST) ?: 'N/A');
            $seenDateRaw = trim((string) ($article['seendate'] ?? ''));

            $publishedAt = $this->parseSeenDate($seenDateRaw);

            if ($publishedAt instanceof Carbon && $publishedAt->lt($freshThreshold)) {
                continue;
            }

            $canonicalUrl = preg_replace('/\?.*$/', '', $sourceUrl);
            $signature = sha1(Str::lower($headline).'|'.$canonicalUrl);

            $items[] = [
                'headline' => $headline,
                'summary' => $headline, // GDELT artlist mode has no snippet — title is the only text.
                'source_name' => $sourceName,
                'source_url' => $sourceUrl,
                'published_at' => $publishedAt,
                'signature' => $signature,
                'tokens' => HeadlineTokenizer::tokenize($headline),
            ];
        }

        usort($items, fn ($a, $b) => ($b['published_at']?->timestamp ?? 0) <=> ($a['published_at']?->timestamp ?? 0));

        return $items;
    }

    /**
     * Parse GDELT seendate format: YYYYMMDDTHHMMSSZ (e.g. 20260908T143000Z).
     */
    private function parseSeenDate(string $seenDate): ?Carbon
    {
        if ($seenDate === '') {
            return null;
        }

        // GDELT format: 20260908T143000Z → 2026-09-08 14:30:00
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z$/', $seenDate, $m)) {
            try {
                return Carbon::createFromFormat('Y-m-d H:i:s', "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}", 'UTC');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($seenDate);
        } catch (\Throwable) {
            return null;
        }
    }
}
