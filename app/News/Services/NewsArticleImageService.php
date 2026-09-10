<?php

namespace App\News\Services;

use App\News\Support\GoogleNewsUrlDecoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsArticleImageService
{
    /**
     * Reject obvious non-article assets (logos/icons/placeholders) that hurt feed quality.
     *
     * @var array<int, string>
     */
    private array $blockedImageFragments = [
        '/favicon',
        'favicon.',
        '/logo',
        'logo.',
        'apple-touch-icon',
        'site-icon',
        '/icon',
        'sprite',
        'placeholder',
        'default-image',
        'news.google.com',
        'gstatic.com',
        'googleusercontent.com',
        'gravatar.com',
        '1x1',
        'pixel.gif',
        'blank.gif',
        'transparent.gif',
        'tracking-pixel',
        'ad-banner',
    ];

    private string $userAgent = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    /**
     * @param  array{id:int,category:string,topic_name:string,sources:array<int,array<string,mixed>>}  $topic
     * @return array{image_url:?string,thumbnail_url:?string,image_origin:?string}
     */
    public function resolveImageForTopic(array $topic, string $articleTitle): array
    {
        if (! (bool) config('news-engine.images.enabled', true)) {
            return ['image_url' => null, 'thumbnail_url' => null, 'image_origin' => null];
        }

        $sourceImage = $this->resolveFromSources($topic['sources'] ?? []);
        if ($sourceImage !== null) {
            return [
                'image_url' => $sourceImage,
                'thumbnail_url' => $sourceImage,
                'image_origin' => 'source',
            ];
        }

        // Source scraping failed (usually Google News redirects that bot-block).
        // Try the Brave Images API next — real photos without AI generation.
        $braveImage = $this->fetchFromBraveImages(
            (string) ($topic['topic_name'] ?? ''),
            $articleTitle,
        );
        if ($braveImage !== null) {
            return [
                'image_url' => $braveImage,
                'thumbnail_url' => $braveImage,
                'image_origin' => 'brave',
            ];
        }

        // Last resort: AI generation (config-gated, off in prod) — keeps
        // articles illustrated when neither sources nor Brave yield an image.
        $aiImage = $this->generateAiFallbackImage(
            $articleTitle,
            (string) ($topic['topic_name'] ?? ''),
            (string) ($topic['category'] ?? ''),
        );
        if ($aiImage !== null) {
            return [
                'image_url' => $aiImage,
                'thumbnail_url' => $aiImage,
                'image_origin' => 'ai',
            ];
        }

        return ['image_url' => null, 'thumbnail_url' => null, 'image_origin' => null];
    }

    /**
     * @param  array<int,array<string,mixed>>  $sources
     */
    private function resolveFromSources(array $sources): ?string
    {
        if (! (bool) config('news-engine.images.source.enabled', true)) {
            return null;
        }

        $maxAttempts = max(1, (int) config('news-engine.images.source.max_attempts', 3));
        $htmlTimeout = max(5, (int) config('news-engine.images.source.html_timeout', 10));

        // Direct publisher URLs (GDELT, Brave) scrape far more reliably than
        // Google News redirects, which JS-gate or bot-block. Try non-news.google.com
        // sources first, then the redirects. Stable ordering — no shuffling.
        $ordered = $this->orderSourcesForScraping($sources);

        $attempts = 0;
        foreach ($ordered as $source) {
            if ($attempts >= $maxAttempts) {
                break;
            }

            $sourceUrl = trim((string) ($source['source_url'] ?? ''));
            if ($sourceUrl === '') {
                continue;
            }

            // Best-effort: unwrap Google News redirects to the real publisher URL.
            $sourceUrl = GoogleNewsUrlDecoder::decode($sourceUrl);

            $attempts++;

            try {
                $response = Http::timeout($htmlTimeout)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->get($sourceUrl);

                if (! $response->ok()) {
                    continue;
                }

                $imageUrl = $this->extractImageFromHtml((string) $response->body(), $sourceUrl);
                if ($imageUrl === null) {
                    continue;
                }

                $stored = $this->downloadAndStoreImage($imageUrl, 'source');
                if ($stored !== null) {
                    return $stored;
                }
            } catch (\Throwable $e) {
                Log::warning('Source image fetch failed: '.$e->getMessage(), ['source_url' => $sourceUrl]);
            }
        }

        return null;
    }

    /**
     * Query the Brave Images API for a real photo matching the topic/headline.
     * Reuses the same api_key + base_url as the brave_search source so only one
     * credential is configured. Caches the chosen path (or null) per query for
     * 24h so recurring topics don't re-hit the API.
     */
    private function fetchFromBraveImages(string $topicName, string $articleTitle): ?string
    {
        if (! (bool) config('news-engine.images.brave.enabled', true)) {
            return null;
        }

        // Reuse the brave_search source credential — single source of truth.
        $apiKey = (string) config('news-engine.sources.brave_search.api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $query = trim($topicName) !== '' ? trim($topicName) : trim($articleTitle);
        if ($query === '') {
            return null;
        }
        $query = preg_replace('/\s+/', ' ', $query) ?? $query;

        // Cache hits (including null) for 24h — avoids repeat API calls for
        // recurring topic names across the same discovery cycle.
        $cacheKey = 'news-images:brave:'.sha1($query);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $baseUrl = rtrim((string) config(
            'news-engine.sources.brave_search.base_url',
            'https://api.search.brave.com/res/v1',
        ), '/');
        $timeout = max(5, (int) config('news-engine.images.brave.timeout', 15));
        $count = max(1, (int) config('news-engine.images.brave.results_limit', 5));

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'X-Subscription-Token' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($baseUrl.'/images/search', [
                    'q' => $query,
                    'count' => $count,
                    'safesearch' => 'moderate',
                ]);
        } catch (\Throwable $e) {
            // Transient failure — don't cache so the next cycle can retry.
            Log::warning('Brave Images fetch failed: '.$e->getMessage(), ['query' => $query]);

            return null;
        }

        $result = $this->pickBraveImageResult($response);
        // Cache the outcome (path or null) so the same query doesn't re-hit
        // Brave for 24h. Only an actual HTTP response reaches here; exceptions
        // return above without caching to permit retry.
        Cache::put($cacheKey, $result, now()->addHours(24));

        return $result;
    }

    /**
     * Pick the first Brave Images result that passes the blocklist and downloads.
     *
     * @param  \Illuminate\Http\Client\Response  $response
     */
    private function pickBraveImageResult($response): ?string
    {
        if (! $response->ok()) {
            return null;
        }

        $results = $response->json('results', []);
        if (! is_array($results)) {
            return null;
        }

        foreach ($results as $item) {
            $imageUrl = trim((string) ($item['url'] ?? ''));
            if ($imageUrl === '' || ! $this->isLikelyArticleImageUrl($imageUrl)) {
                continue;
            }

            $stored = $this->downloadAndStoreImage($imageUrl, 'brave');
            if ($stored !== null) {
                return $stored;
            }
        }

        return null;
    }

    private function extractImageFromHtml(string $html, string $pageUrl): ?string
    {
        $patterns = [
            '/<meta[^>]+property=["\']og:image:secure_url["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+itemprop=["\']image["\'][^>]+content=["\']([^"\']+)["\']/i',
        ];

        $candidates = [];
        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $html, $matches)) {
                continue;
            }

            foreach (($matches[1] ?? []) as $match) {
                $candidate = trim((string) $match);
                if ($candidate === '' || Str::startsWith($candidate, 'data:')) {
                    continue;
                }
                $candidates[] = $candidate;
            }
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $normalized = $this->normalizeImageUrl($candidate, $pageUrl);
            if ($normalized === null || ! $this->isLikelyArticleImageUrl($normalized)) {
                continue;
            }

            return $normalized;
        }

        return null;
    }

    private function normalizeImageUrl(string $imageUrl, string $pageUrl): ?string
    {
        if (Str::startsWith($imageUrl, ['http://', 'https://'])) {
            return $imageUrl;
        }

        $parts = parse_url($pageUrl);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $base = $parts['scheme'].'://'.$parts['host'];

        if (Str::startsWith($imageUrl, '//')) {
            return $parts['scheme'].':'.$imageUrl;
        }

        if (Str::startsWith($imageUrl, '/')) {
            return $base.$imageUrl;
        }

        $path = $parts['path'] ?? '/';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $base.($dir ? '/'.$dir : '').'/'.$imageUrl;
    }

    private function downloadAndStoreImage(string $url, string $prefix): ?string
    {
        $timeout = max(5, (int) config('news-engine.images.source.image_timeout', 20));

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->get($url);

            if (! $response->ok()) {
                return null;
            }

            $contentType = Str::lower((string) $response->header('Content-Type', ''));
            if (! Str::startsWith($contentType, 'image/')) {
                return null;
            }

            $body = $response->body();
            if (strlen($body) < 20_000) {
                return null;
            }

            if (function_exists('getimagesizefromstring')) {
                $size = @getimagesizefromstring($body);
                $width = (int) ($size[0] ?? 0);
                $height = (int) ($size[1] ?? 0);
                if ($width > 0 && $height > 0 && ($width < 320 || $height < 180)) {
                    return null;
                }
            }

            $extension = match (true) {
                Str::contains($contentType, 'png') => 'png',
                Str::contains($contentType, 'webp') => 'webp',
                Str::contains($contentType, 'gif') => 'gif',
                Str::contains($contentType, 'avif') => 'avif',
                default => 'jpg',
            };

            $path = 'news-images/'.now()->format('Y/m').'/'.$prefix.'-'.Str::uuid().'.'.$extension;
            Storage::disk('public')->put($path, $body);

            return '/storage/'.$path;
        } catch (\Throwable $e) {
            Log::warning('Image download/store failed: '.$e->getMessage(), ['url' => $url]);

            return null;
        }
    }

    private function isLikelyArticleImageUrl(string $url): bool
    {
        $subject = Str::lower($url);

        foreach ($this->blockedImageFragments as $fragment) {
            if (Str::contains($subject, Str::lower($fragment))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Stable sort: direct publisher URLs first, news.google.com redirects last.
     * Preserves original order within each group.
     *
     * @param  array<int,array<string,mixed>>  $sources
     * @return array<int,array<string,mixed>>
     */
    private function orderSourcesForScraping(array $sources): array
    {
        $direct = [];
        $redirects = [];

        foreach ($sources as $source) {
            $url = trim((string) ($source['source_url'] ?? ''));
            $host = $url !== '' ? parse_url($url, PHP_URL_HOST) : null;
            if ($host === 'news.google.com') {
                $redirects[] = $source;
            } else {
                $direct[] = $source;
            }
        }

        return array_merge($direct, $redirects);
    }

    private function generateAiFallbackImage(string $articleTitle, string $topicName, string $category): ?string
    {
        if (! (bool) config('news-engine.images.ai.enabled', true)) {
            return null;
        }

        $provider = (string) config('news-engine.images.ai.provider', 'pollinations');

        return match ($provider) {
            'pollinations' => $this->generateWithPollinations($articleTitle, $topicName, $category),
            default => null,
        };
    }

    private function generateWithPollinations(string $articleTitle, string $topicName, string $category): ?string
    {
        $baseUrl = rtrim((string) config('news-engine.images.ai.pollinations.base_url', 'https://image.pollinations.ai'), '/');
        $model = (string) config('news-engine.images.ai.pollinations.model', 'flux');
        $width = max(640, (int) config('news-engine.images.ai.pollinations.width', 1536));
        $height = max(360, (int) config('news-engine.images.ai.pollinations.height', 864));
        $timeout = max(10, (int) config('news-engine.images.ai.pollinations.timeout', 35));
        $style = (string) config('news-engine.images.ai.style_prompt');

        $prompt = trim(
            $style.' '
            .'Topic: '.$topicName.'. '
            .'Category: '.$category.'. '
            .'Headline: '.$articleTitle.'.'
        );

        $url = $baseUrl.'/prompt/'.rawurlencode($prompt)
            .'?model='.rawurlencode($model)
            .'&width='.$width
            .'&height='.$height
            .'&nologo=true'
            .'&safe=true'
            .'&seed='.random_int(1, 999999);

        try {
            $response = Http::timeout($timeout)->get($url);

            if (! $response->ok()) {
                return null;
            }

            $contentType = Str::lower((string) $response->header('Content-Type', 'image/jpeg'));
            if (! Str::startsWith($contentType, 'image/')) {
                return null;
            }

            $extension = match (true) {
                Str::contains($contentType, 'png') => 'png',
                Str::contains($contentType, 'webp') => 'webp',
                default => 'jpg',
            };

            $path = 'news-images/'.now()->format('Y/m').'/ai-'.Str::uuid().'.'.$extension;
            Storage::disk('public')->put($path, $response->body());

            return '/storage/'.$path;
        } catch (\Throwable $e) {
            Log::warning('AI image generation failed: '.$e->getMessage());

            return null;
        }
    }
}
