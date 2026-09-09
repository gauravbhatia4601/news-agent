<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\News\Sources\GdeltSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GdeltSourceTest extends TestCase
{
    /**
     * GDELT returns articles with seendate in YYYYMMDDTHHMMSSZ format.
     * fetch() parses them to Carbon, filters out-of-window items, and
     * includes the timespan param in the request URL.
     */
    public function test_fetch_parses_gdelt_response_and_filters_out_of_window_articles(): void
    {
        $freshThreshold = now()->subMinutes(30);

        Http::fake([
            'api.gdeltproject.org/*' => Http::response([
                'articles' => [
                    [
                        'title' => 'Breaking news event unfolds in real time',
                        'url' => 'https://example.com/article1?utm=1',
                        'domain' => 'example.com',
                        'seendate' => now()->format('Ymd\THis\Z'),
                    ],
                    [
                        'title' => 'Another source confirms the development',
                        'url' => 'https://reuters.com/article2',
                        'domain' => 'reuters.com',
                        'seendate' => now()->subMinutes(10)->format('Ymd\THis\Z'),
                    ],
                    [
                        'title' => 'Old article outside the fresh threshold window',
                        'url' => 'https://bbc.com/article3',
                        'domain' => 'bbc.com',
                        'seendate' => now()->subHours(3)->format('Ymd\THis\Z'),
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $source = new GdeltSource([
            'base_url' => 'https://api.gdeltproject.org/api/v2/doc/doc',
            'timeout' => 10,
            'maxrecords' => 75,
        ]);

        $results = $source->fetch('breaking news event', $freshThreshold, 40, 'india', '15min');

        // Out-of-window article filtered out.
        $this->assertCount(2, $results);

        // Candidate shape.
        $first = $results[0];
        $this->assertArrayHasKey('headline', $first);
        $this->assertArrayHasKey('summary', $first);
        $this->assertArrayHasKey('source_name', $first);
        $this->assertArrayHasKey('source_url', $first);
        $this->assertArrayHasKey('published_at', $first);
        $this->assertArrayHasKey('signature', $first);
        $this->assertArrayHasKey('tokens', $first);

        // published_at is a Carbon instance.
        $this->assertInstanceOf(Carbon::class, $first['published_at']);

        // summary = title for GDELT (no snippet in artlist mode).
        $this->assertSame($first['headline'], $first['summary']);

        // signature is sha1 of lowercased title + canonical URL.
        $canonicalUrl = preg_replace('/\?.*$/', '', 'https://example.com/article1?utm=1');
        $expectedSig = sha1(strtolower('Breaking news event unfolds in real time').'|'.$canonicalUrl);
        $this->assertSame($expectedSig, $first['signature']);

        // tokens from HeadlineTokenizer — stopwords removed, length > 2.
        $this->assertNotContains('the', $first['tokens']);
        $this->assertNotContains('in', $first['tokens']);
        $this->assertContains('breaking', $first['tokens']);
        $this->assertContains('event', $first['tokens']);
        $this->assertContains('unfolds', $first['tokens']);
        $this->assertContains('real', $first['tokens']);
        $this->assertContains('time', $first['tokens']);
    }

    /**
     * The timespan freshness param appears in the GDELT request URL.
     */
    public function test_fetch_includes_timespan_in_request_url(): void
    {
        Http::fake([
            'api.gdeltproject.org/*' => Http::response(['articles' => []], 200),
        ]);

        $source = new GdeltSource([
            'base_url' => 'https://api.gdeltproject.org/api/v2/doc/doc',
            'timeout' => 10,
            'maxrecords' => 75,
        ]);

        $source->fetch('live event query', now()->subHours(1), 40, 'india', '15min');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'timespan=15min')
                && str_contains($request->url(), 'mode=artlist')
                && str_contains($request->url(), 'format=json');
        });
    }
}
