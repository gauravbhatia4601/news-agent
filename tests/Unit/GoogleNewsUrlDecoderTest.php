<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\News\Support\GoogleNewsUrlDecoder;
use Tests\TestCase;

class GoogleNewsUrlDecoderTest extends TestCase
{
    /**
     * Build a CBMi payload the same way current Google News RSS does:
     * after the CBMi prefix the bytes are `0x08 <varint-len> <url-bytes>`,
     * base64url-encoded.
     */
    private function buildGoogleUrl(string $realUrl, bool $withLeadingOne = false): string
    {
        $bytes = ($withLeadingOne ? chr(0x01) : '').chr(0x08).chr(strlen($realUrl)).$realUrl;
        $b64 = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return 'https://news.google.com/rss/articles/CBMi'.$b64;
    }

    public function test_decode_extracts_real_url_from_google_redirect(): void
    {
        $real = 'https://www.thehindu.com/news/national/parliament-passes-bill-123456789/article.ece';
        $wrapped = $this->buildGoogleUrl($real);

        $this->assertSame($real, GoogleNewsUrlDecoder::decode($wrapped));
    }

    public function test_decode_handles_leading_01_byte_in_payload(): void
    {
        $real = 'https://www.bbc.com/news/world-asia-india-654321';
        $wrapped = $this->buildGoogleUrl($real, true);

        $this->assertSame($real, GoogleNewsUrlDecoder::decode($wrapped));
    }

    public function test_decode_passes_through_non_google_urls_unchanged(): void
    {
        $direct = 'https://www.reuters.com/world/asia-pacific/some-article-2024-09-10/';

        $this->assertSame($direct, GoogleNewsUrlDecoder::decode($direct));
    }

    public function test_decode_returns_original_on_garbage_payload(): void
    {
        $garbage = 'https://news.google.com/rss/articles/CBMiZZnotvalidbase64!!';

        $this->assertSame($garbage, GoogleNewsUrlDecoder::decode($garbage));
    }

    public function test_decode_returns_original_when_payload_does_not_start_with_http(): void
    {
        // Valid base64 but the decoded bytes do not contain a URL.
        $bytes = chr(0x08).chr(5).'hello';
        $b64 = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        $url = 'https://news.google.com/articles/CBMi'.$b64;

        $this->assertSame($url, GoogleNewsUrlDecoder::decode($url));
    }

    public function test_decode_returns_original_for_google_url_without_cbmi_segment(): void
    {
        $url = 'https://news.google.com/topics/CAAqJQgKIh9DQklTRFFnSUtnWGxMbU52YldSbU5ERWFMQW9D';

        $this->assertSame($url, GoogleNewsUrlDecoder::decode($url));
    }
}
