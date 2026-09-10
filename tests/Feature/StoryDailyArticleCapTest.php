<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Services\LiveStoryAgentService;
use App\Jobs\GenerateArticle;
use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class StoryDailyArticleCapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * When the per-story daily article cap is reached, the monitor skips
     * GenerateArticle dispatch but still creates the timeline entry and
     * links the topic via pivot.
     */
    public function test_cap_reached_skips_dispatch_but_creates_timeline(): void
    {
        // Cap = 1: one published article in the last 24h blocks all dispatches.
        config(['news-engine.live_stories.max_supporting_articles_per_day' => 1]);

        $parent = Category::create(['name' => 'National', 'slug' => 'national', 'display_order' => 1]);
        Category::create([
            'name' => 'Politics & Governance',
            'slug' => 'politics-governance',
            'parent_id' => $parent->id,
            'display_order' => 1,
        ]);

        $story = Story::factory()->live()->create([
            'search_query' => 'India election reform bill',
        ]);

        // Pre-publish one article for this story — cap is now reached.
        $capTopic = NewsTopic::create([
            'category' => 'national',
            'topic_name' => 'Flood rescue operations in Kerala',
            'topic_signature' => 'sig-cap-'.uniqid(),
            'source_count' => 2,
            'generation_status' => 'generated',
        ]);
        NewsArticle::create([
            'topic_id' => $capTopic->id,
            'story_id' => $story->id,
            'title' => 'Flood rescue operations continue in Kerala coastal districts',
            'content' => '<p>Body</p>',
            'slug' => 'cap-'.uniqid(),
            'status' => 'published',
            'published_at' => now()->subMinutes(30),
        ]);

        Http::fake([
            'news.google.com/*' => Http::response($this->googleRssXml(), 200, ['Content-Type' => 'application/rss+xml']),
            'api.gdeltproject.org/*' => Http::response([
                'articles' => [
                    [
                        'title' => 'Parliament passes election reform bill in historic vote',
                        'url' => 'https://example.com/gdelt-reform',
                        'domain' => 'example.com',
                        'seendate' => now()->format('Ymd\THis\Z'),
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
            'api.search.brave.com/*' => Http::response([
                'grounding' => ['sources' => []],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        Cache::flush();
        Queue::fake();

        // Judge marks the candidate as a new development.
        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('judgeUpdateBatch')
            ->once()
            ->andReturnUsing(function ($storyArg, $candidates, $lastUpdate) {
                $verdicts = [];
                foreach ($candidates as $candidate) {
                    $verdicts[$candidate['topic_signature']] = [
                        'topic_signature' => $candidate['topic_signature'],
                        'is_new_development' => true,
                        'urgency_adjustment' => 'keep',
                        'update_text' => 'Parliament passes the election reform bill in a historic vote.',
                        'reasoning' => 'New phase in the legislative process.',
                    ];
                }

                return $verdicts;
            });

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:monitor-stories')->assertSuccessful();

        // NO GenerateArticle dispatched — cap reached.
        Queue::assertNotPushed(GenerateArticle::class);

        // Timeline entry still created.
        $updateCount = DB::table('story_updates')->where('story_id', $story->id)->count();
        $this->assertGreaterThan(0, $updateCount, 'Timeline entry must still be created when cap is reached.');

        // Pivot still linked.
        $pivotCount = DB::table('story_topics')->where('story_id', $story->id)->count();
        $this->assertGreaterThan(0, $pivotCount, 'Pivot link must still be created when cap is reached.');
    }

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
  </channel>
</rss>
XML;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
