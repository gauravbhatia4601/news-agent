<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\News\Support\GoogleNewsUrlDecoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    /**
     * Build an opaque-token Google News URL (the new shape where the CBMi
     * payload decodes to an AU_… token, NOT a URL).
     */
    private function buildOpaqueTokenUrl(string $token = 'AU_xenotarealtoken1234567890'): string
    {
        $bytes = $token;
        $b64 = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return 'https://news.google.com/rss/articles/CBMi'.$b64;
    }

    /**
     * Build a realistic batchexecute response body: )]}'-prefixed,
     * line-delimited JSON arrays, with the publisher URL nested inside
     * a stringified inner JSON array (the garturlres shape).
     */
    private function buildBatchexecuteResponse(string $publisherUrl): string
    {
        // Inner data element: a stringified JSON array containing the URL.
        $inner = json_encode([$publisherUrl], JSON_UNESCAPED_SLASHES);
        // Outer wrapper: [["Fbv4je", "{inner-as-string}"]], null, "generic"]
        $row = json_encode([['Fbv4je', $inner]], JSON_UNESCAPED_SLASHES);

        return ")]}'\n".$row;
    }

    /**
     * Build a realistic Google News wrapper page carrying the signed
     * attributes the batchexecute RPC requires.
     */
    private function buildWrapperPage(string $articleId): string
    {
        return '<html><body><c-wiz data-n-a-id="'.$articleId.'"'
            .' data-n-a-ts="1790439596"'
            .' data-n-a-sg="AbIaSL-u8jrGo8iiwrEMjJBkrluM">x</c-wiz></body></html>';
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Each test starts with a cold cache.
        Cache::flush();
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

    public function test_decode_resolves_opaque_token_via_batchexecute(): void
    {
        $url = $this->buildOpaqueTokenUrl();
        $articleId = substr($url, strpos($url, 'CBMi') + 0); // full CBMi… id
        $publisher = 'https://www.reuters.com/world/asia-pacific/real-article-2026-09-11/';

        Http::fake([
            $url => Http::response($this->buildWrapperPage($articleId), 200, ['Content-Type' => 'text/html']),
            'news.google.com/_/DotsSplashUi/*' => Http::response($this->buildBatchexecuteResponse($publisher), 200, ['Content-Type' => 'text/plain']),
        ]);

        $resolved = GoogleNewsUrlDecoder::decode($url);

        $this->assertSame($publisher, $resolved);
        Http::assertSentCount(2); // wrapper page + RPC
    }

    public function test_decode_caches_batchexecute_resolution(): void
    {
        $url = $this->buildOpaqueTokenUrl();
        $articleId = substr($url, strpos($url, 'CBMi') + 0);
        $publisher = 'https://www.bbc.com/news/world-asia-india-998877';

        Http::fake([
            $url => Http::response($this->buildWrapperPage($articleId), 200, ['Content-Type' => 'text/html']),
            'news.google.com/_/DotsSplashUi/*' => Http::response($this->buildBatchexecuteResponse($publisher), 200, ['Content-Type' => 'text/plain']),
        ]);

        // First call hits HTTP.
        $this->assertSame($publisher, GoogleNewsUrlDecoder::decode($url));
        // Second call must come from cache — HTTP count stays at 2.
        $this->assertSame($publisher, GoogleNewsUrlDecoder::decode($url));

        Http::assertSentCount(2);
    }

    public function test_decode_passthrough_when_wrapper_page_lacks_signature(): void
    {
        $url = $this->buildOpaqueTokenUrl();

        Http::fake([
            $url => Http::response('<html><body>no attrs</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->assertSame($url, GoogleNewsUrlDecoder::decode($url));
        Http::assertSentCount(1); // wrapper only — no RPC without a signature
    }

    public function test_decode_passthrough_when_batchexecute_returns_no_url(): void
    {
        $url = $this->buildOpaqueTokenUrl();
        $articleId = substr($url, strpos($url, 'CBMi') + 0);

        Http::fake([
            $url => Http::response($this->buildWrapperPage($articleId), 200, ['Content-Type' => 'text/html']),
            'news.google.com/_/DotsSplashUi/*' => Http::response(")]}'\n[[\"garbage\"]]", 200, ['Content-Type' => 'text/plain']),
        ]);

        $this->assertSame($url, GoogleNewsUrlDecoder::decode($url));
    }

    public function test_decode_passthrough_when_batchexecute_returns_google_only_urls(): void
    {
        $url = $this->buildOpaqueTokenUrl();
        $articleId = substr($url, strpos($url, 'CBMi') + 0);

        // Response only contains Google hosts — no publisher URL to extract.
        $body = ")]}'\n[\"https://news.google.com/foo https://google.com/bar\"]";

        Http::fake([
            $url => Http::response($this->buildWrapperPage($articleId), 200, ['Content-Type' => 'text/html']),
            'news.google.com/_/DotsSplashUi/*' => Http::response($body, 200, ['Content-Type' => 'text/plain']),
        ]);

        $this->assertSame($url, GoogleNewsUrlDecoder::decode($url));
    }

    public function test_decode_passthrough_when_batchexecute_errors(): void
    {
        $url = $this->buildOpaqueTokenUrl();
        $articleId = substr($url, strpos($url, 'CBMi') + 0);

        Http::fake([
            $url => Http::response($this->buildWrapperPage($articleId), 200, ['Content-Type' => 'text/html']),
            'news.google.com/_/DotsSplashUi/*' => Http::response('server error', 500),
        ]);

        $this->assertSame($url, GoogleNewsUrlDecoder::decode($url));
    }

    public function test_decode_skips_batchexecute_when_config_disabled(): void
    {
        config(['news-engine.images.source.resolve_google_redirects' => false]);
        $url = $this->buildOpaqueTokenUrl();

        Http::fake([
            $url => Http::response($this->buildWrapperPage($url), 200),
            'news.google.com/_/DotsSplashUi/*' => Http::response($this->buildBatchexecuteResponse('https://example.com/article'), 200),
        ]);

        // No HTTP call, original URL returned.
        $this->assertSame($url, GoogleNewsUrlDecoder::decode($url));
        Http::assertNothingSent();
    }

    public function test_decode_batchexecute_sends_garturlreq_payload_shape(): void
    {
        $url = $this->buildOpaqueTokenUrl();
        $articleId = substr($url, strpos($url, 'CBMi') + 0);
        $publisher = 'https://example.com/news/story';

        Http::fake([
            $url => Http::response($this->buildWrapperPage($articleId), 200, ['Content-Type' => 'text/html']),
            'news.google.com/_/DotsSplashUi/*' => Http::response($this->buildBatchexecuteResponse($publisher), 200, ['Content-Type' => 'text/plain']),
        ]);

        GoogleNewsUrlDecoder::decode($url);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $body = $request->body();

            // The signed garturlreq shape — not the legacy gartsId object.
            return $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
                && str_contains($body, 'garturlreq')
                && str_contains($body, '1790439596')
                && str_contains($body, 'AbIaSL-u8jrGo8iiwrEMjJBkrluM')
                && ! str_contains($body, 'gartsId');
        });
    }
}
