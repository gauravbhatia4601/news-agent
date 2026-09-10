<?php

declare(strict_types=1);

namespace App\News\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Best-effort decoder for Google News RSS redirect URLs.
 *
 * Google News RSS wraps the real publisher URL inside a base64url payload
 * after the "/articles/CBMi" path segment. Two payload shapes exist:
 *
 *  1. EMBEDDED-URL payloads (older / still seen on some feeds): the bytes
 *     after CBMi are a protobuf wire layout (`0x08 <varint-len> <url-bytes>`)
 *     whose value IS the publisher URL. `extractUrlFromProtobuf` handles
 *     this locally and for free.
 *  2. OPAQUE-TOKEN payloads (current default): the bytes after CBMi decode
 *     to an opaque token (starts `AU_`) that is NOT a URL. The publisher URL
 *     must be resolved via Google's undocumented `batchexecute` endpoint.
 *     `resolveViaBatchexecute` handles this with a single cached HTTP call.
 *
 * On any failure the original URL is returned unchanged — callers should
 * scrape as-is (current behavior) rather than drop the source. The HTTP
 * resolution is gated by `news-engine.images.source.resolve_google_redirects`
 * and is only attempted for `news.google.com` hosts; it is cached for 7 days
 * per source URL so a source resolves once, never again.
 */
final class GoogleNewsUrlDecoder
{
    /** Cache TTL for batchexecute resolutions (seconds). */
    private const CACHE_TTL = 604800; // 7 days

    /** batchexecute endpoint. */
    private const BATCH_EXECUTE_URL = 'https://news.google.com/_/DotsSplashUi/data/batchexecute';

    /** HTTP timeout for batchexecute (seconds). */
    private const HTTP_TIMEOUT = 10;

    /**
     * Decode a Google News redirect URL to its underlying publisher URL.
     *
     * @param  string  $url  The source URL (may be a news.google.com redirect or a direct publisher URL).
     * @return string The resolved URL, or the original input on any failure.
     */
    public static function decode(string $url): string
    {
        try {
            $host = parse_url($url, PHP_URL_HOST);
            $path = (string) parse_url($url, PHP_URL_PATH);

            if ($host !== 'news.google.com') {
                return $url;
            }

            // Match the payload after "/articles/" — capture the full ID
            // including the CBMi prefix (e.g. "CBMi<AU_...>"). Paths look
            // like "/rss/articles/CBMi..." or "/articles/CBMi...".
            if (! preg_match('#/articles/(CBMi[A-Za-z0-9_\-]+)#', $url, $m)) {
                return $url;
            }

            $fullId = $m[1];            // "CBMi..." — the full path token
            $b64Part = substr($fullId, 4); // bytes after "CBMi"

            $payload = self::base64UrlDecode($b64Part);
            if ($payload === null) {
                return $url;
            }

            // Path 1: the payload embeds the URL directly (local, free).
            $resolved = self::extractUrlFromProtobuf($payload);
            if ($resolved !== null) {
                return $resolved;
            }

            // Path 2: opaque token — resolve via batchexecute (HTTP, cached).
            if (config('news-engine.images.source.resolve_google_redirects', true)) {
                return self::resolveViaBatchexecute($url, $fullId);
            }

            return $url;
        } catch (\Throwable) {
            return $url;
        }
    }

    /**
     * Resolve an opaque Google News article token via the batchexecute RPC.
     *
     * Sends a single POST with the full article ID (CBMi…) and parses the
     * `)]}'`-prefixed line-delimited JSON response for the first plausible
     * publisher URL. Cached for 7 days per source URL. ANY failure returns
     * the original URL unchanged — never throws.
     *
     * @param  string  $sourceUrl  The original news.google.com URL (cache key + fallback).
     * @param  string  $fullId  The full path token ("CBMi…") as it appears in the URL path.
     */
    private static function resolveViaBatchexecute(string $sourceUrl, string $fullId): string
    {
        $cacheKey = 'gnews:resolve:'.sha1($sourceUrl);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            // f.req payload: [[["Fbv4je","{\"gartsId\":\"<fullId>\"}",null,"generic"]]]
            $inner = json_encode(['gartsId' => $fullId], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $freq = json_encode([['Fbv4je', $inner, null, 'generic']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $response = Http::asForm()
                ->timeout(self::HTTP_TIMEOUT)
                ->connectTimeout(self::HTTP_TIMEOUT)
                ->post(self::BATCH_EXECUTE_URL, ['f.req' => $freq]);

            if (! $response->successful()) {
                return $sourceUrl;
            }

            $body = $response->body();
            $resolved = self::extractPublisherUrlFromBody($body);
            if ($resolved === null) {
                return $sourceUrl;
            }

            Cache::put($cacheKey, $resolved, self::CACHE_TTL);

            return $resolved;
        } catch (\Throwable) {
            return $sourceUrl;
        }
    }

    /**
     * Parse the batchexecute response body for the first plausible publisher URL.
     *
     * The response is `)]}'`-prefixed line-delimited JSON arrays. The real
     * URL appears as a JSON string inside the data element. We parse
     * defensively: find all `https://` strings via regex and return the
     * first one that is NOT a google.com / news.google.com host.
     */
    private static function extractPublisherUrlFromBody(string $body): ?string
    {
        // Strip the XSSI prefix if present.
        $body = ltrim($body);
        if (str_starts_with($body, ")]}'")) {
            $body = substr($body, 4);
        }

        // Find every https:// substring up to the next whitespace/quote/angle.
        if (! preg_match_all('#https://[^\s"\'<>\\\\]+#', $body, $matches)) {
            return null;
        }

        foreach ($matches[0] as $candidate) {
            $host = parse_url($candidate, PHP_URL_HOST);
            if ($host === null) {
                continue;
            }
            // Skip Google's own hosts — we want the publisher URL.
            if ($host === 'news.google.com' || $host === 'google.com' || str_ends_with($host, '.google.com') || str_ends_with($host, '.googleusercontent.com')) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private static function base64UrlDecode(string $input): ?string
    {
        $padded = strtr($input, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }

    /**
     * Walk the protobuf wire layout used by embedded-URL Google News
     * payloads: after the CBMi prefix the bytes are
     * `0x08 <varint-len> <url-bytes>`, sometimes preceded by a leading
     * 0x01 byte. Read the varint length, then consume that many bytes as a
     * UTF-8 string and validate it looks like a URL.
     */
    private static function extractUrlFromProtobuf(string $bytes): ?string
    {
        $offset = 0;

        // Strip an optional leading tag byte (0x01) seen in some payloads.
        if (strlen($bytes) > 0 && ord($bytes[0]) === 0x01) {
            $offset = 1;
        }

        // Expect a field-1 length-delimited tag (0x08) followed by a varint length.
        if (strlen($bytes) < $offset + 2) {
            return null;
        }
        if (ord($bytes[$offset]) !== 0x08) {
            return null;
        }
        $offset++;

        [$length, $lenBytes] = self::readVarint($bytes, $offset);
        if ($lenBytes === 0) {
            return null;
        }
        $offset += $lenBytes;

        if ($length <= 0 || strlen($bytes) < $offset + $length) {
            return null;
        }

        $candidate = substr($bytes, $offset, $length);
        if (! str_starts_with($candidate, 'http')) {
            return null;
        }

        return $candidate;
    }

    /**
     * @return array{0:int,1:int} [value, bytes_consumed] or [0, 0] on failure.
     */
    private static function readVarint(string $bytes, int $offset): array
    {
        $result = 0;
        $shift = 0;
        $consumed = 0;

        for ($i = $offset; $i < strlen($bytes); $i++) {
            $byte = ord($bytes[$i]);
            $result |= ($byte & 0x7F) << $shift;
            $consumed++;

            if (($byte & 0x80) === 0) {
                return [$result, $consumed];
            }

            $shift += 7;
            if ($shift > 35) {
                return [0, 0];
            }
        }

        return [0, 0];
    }
}
