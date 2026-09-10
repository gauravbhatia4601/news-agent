<?php

declare(strict_types=1);

namespace App\News\Support;

/**
 * Best-effort decoder for Google News RSS redirect URLs.
 *
 * Google News RSS wraps the real publisher URL inside a protobuf-encoded
 * payload after the "/articles/CBMi" path segment. This extracts the direct
 * URL so source scrapers hit the actual article page instead of a JS-gated
 * Google redirect that bot-blocks.
 *
 * On any failure the original URL is returned unchanged — callers should
 * scrape as-is (current behavior) rather than drop the source.
 */
final class GoogleNewsUrlDecoder
{
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

            // Match the payload after "CBMi" in paths like
            // "/rss/articles/CBMi..." or "/articles/CBMi...".
            if (! preg_match('#/articles/CBMi([A-Za-z0-9_\-]+)#', $url, $m)) {
                return $url;
            }

            $payload = self::base64UrlDecode($m[1]);
            if ($payload === null) {
                return $url;
            }

            $resolved = self::extractUrlFromProtobuf($payload);
            if ($resolved === null) {
                return $url;
            }

            return $resolved;
        } catch (\Throwable) {
            return $url;
        }
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
     * Walk the protobuf wire layout used by current Google News payloads:
     * after the CBMi prefix the bytes are `0x08 <varint-len> <url-bytes>`,
     * sometimes preceded by a leading 0x01 byte. Read the varint length,
     * then consume that many bytes as a UTF-8 string and validate it looks
     * like a URL.
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
