<?php

namespace App\News\Services;

use App\News\Support\GoogleNewsUrlDecoder;
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

        // No image from publisher pages → article ships unillustrated.
        // (Former Brave Images + AI fallbacks removed 2026-09-27: the
        // batchexecute URL decode makes source scraping reliable, and real
        // article photos beat AI-generated or topic-matched stock.)
        return ['image_url' => null, 'thumbnail_url' => null, 'image_origin' => null];
    }

    /**
     * Scrape the article's publisher pages for an og:image/twitter:image.
     *
     * Google News redirect URLs are decoded to the real publisher URL first
     * (batchexecute for opaque CBMi tokens), then the page is fetched and its
     * meta image extracted, downloaded, validated and stored locally.
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
}
