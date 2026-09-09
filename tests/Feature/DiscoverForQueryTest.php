<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Story;
use App\News\Services\NewsDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscoverForQueryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * discoverForQuery fetches from Google RSS + GDELT + Brave, clusters
     * candidates, saves topics with a story-namespaced signature, and
     * marks source signatures as seen.
     */
    public function test_discover_for_query_creates_topics_with_story_namespaced_signatures(): void
    {
        // Seed a category the detection service can match (politics-governance slug).
        $parent = Category::create(['name' => 'National', 'slug' => 'national', 'display_order' => 1]);
        Category::create([
            'name' => 'Politics & Governance',
            'slug' => 'politics-governance',
            'parent_id' => $parent->id,
            'display_order' => 1,
        ]);

        $story = Story::factory()->live()->create([
            'search_query' => 'India parliament election',
        ]);

        // Fake all three source endpoints.
        Http::fake([
            'news.google.com/*' => Http::response($this->googleRssXml(), 200, ['Content-Type' => 'application/rss+xml']),
            'api.gdeltproject.org/*' => Http::response([
                'articles' => [
                    [
                        'title' => 'Parliament passes landmark election reform bill today',
                        'url' => 'https://example.com/gdelt-election-reform',
                        'domain' => 'example.com',
                        'seendate' => now()->format('Ymd\THis\Z'),
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
            'api.search.brave.com/*' => Http::response([
                'grounding' => [
                    'sources' => [
                        'https://reuters.com/india-election-reform' => [
                            'title' => 'India election reform bill clears parliament',
                            'description' => 'Parliament passed the landmark election reform bill.',
                        ],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        Cache::flush();

        $service = app(NewsDiscoveryService::class);
        $freshness = [
            'google' => '1h',
            'brave' => 'ph',
            'gdelt' => '15min',
            'hours' => 1,
        ];

        $topics = $service->discoverForQuery(
            'India parliament election',
            $freshness,
            freshHours: 1,
            limit: 3,
            sourcesPerTopic: 3,
            storyId: $story->id,
        );

        // At least one topic should be clustered from the 3 sources.
        $this->assertNotEmpty($topics, 'Expected at least one clustered topic from the three sources.');

        // The topic signature must be derived from the story-namespaced slug
        // (sha1 of "story:{id}|coreTokens"), not the raw query slug.
        $firstTopic = $topics[0];
        $nonNamespacedSig = sha1(strtolower('India parliament election').'|'.implode('|', $firstTopic->coreTokens));
        $this->assertNotSame(
            $nonNamespacedSig,
            $firstTopic->signature,
            'Topic signature should be namespaced with the story slug, not the raw query.',
        );

        // Verify the signature is the sha1 of story:{id}|coreTokens.
        $expectedSig = sha1("story:{$story->id}|".implode('|', $firstTopic->coreTokens));
        $this->assertSame($expectedSig, $firstTopic->signature);

        // The topic should have been persisted to the database.
        $this->assertDatabaseHas('news_topics', [
            'topic_signature' => $firstTopic->signature,
        ]);

        // Source signatures should be marked as seen in the cache.
        $seen = Cache::get(config('news-engine.discovery.seen_cache_key'));
        $this->assertIsArray($seen);
        $this->assertNotEmpty($seen, 'Source signatures should be marked as seen in cache.');
    }

    /**
     * Minimal Google News RSS XML response with two items that share enough
     * tokens to cluster together with the GDELT + Brave articles.
     */
    private function googleRssXml(): string
    {
        $pubDate = now()->subMinutes(20)->format('D, d M Y H:i:s O');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Google News</title>
    <item>
      <title>Parliament passes landmark election reform bill - Reuters</title>
      <link>https://reuters.com/google-redirect-election-reform</link>
      <description>&lt;a href="https://reuters.com/india-election-reform"&gt;Parliament passes election reform bill&lt;/a&gt;</description>
      <pubDate>{$pubDate}</pubDate>
    </item>
    <item>
      <title>India election reform clears parliament in historic vote - Hindu</title>
      <link>https://thehindu.com/google-redirect-india-election</link>
      <description>&lt;a href="https://thehindu.com/india-election-reform"&gt;Election reform clears parliament&lt;/a&gt;</description>
      <pubDate>{$pubDate}</pubDate>
    </item>
  </channel>
</rss>
XML;
    }
}
