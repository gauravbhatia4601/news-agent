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

class MonitorLiveStoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Monitor discovers candidates, judge marks one as new development,
     * GenerateArticle is dispatched with storyId, last_monitored_at is set.
     */
    public function test_monitor_dispatches_for_new_development_and_sets_last_monitored(): void
    {
        // Seed category for topic detection.
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

        // Fake all three source endpoints.
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
                    [
                        'title' => 'Opposition party challenges election reform in Supreme Court',
                        'url' => 'https://example.com/gdelt-challenge',
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
        Queue::fake();

        // Mock the judge: first candidate is new development, second is not.
        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('judgeUpdateBatch')
            ->once()
            ->andReturnUsing(function ($storyArg, $candidates, $lastUpdate) {
                $verdicts = [];
                foreach ($candidates as $i => $candidate) {
                    $verdicts[$candidate['topic_signature']] = [
                        'topic_signature' => $candidate['topic_signature'],
                        'is_new_development' => $i === 0,
                        'urgency_adjustment' => 'keep',
                        'update_text' => $i === 0 ? 'Parliament passes the election reform bill in a historic vote.' : 'Repetition of existing coverage.',
                        'reasoning' => $i === 0 ? 'New phase in the legislative process.' : 'Repetition of existing coverage.',
                    ];
                }

                return $verdicts;
            });

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:monitor-stories')->assertSuccessful();

        // GenerateArticle dispatched at least once with the story ID.
        Queue::assertPushed(GenerateArticle::class, function ($job) use ($story) {
            return $job->storyId === $story->id;
        });

        // last_monitored_at is set.
        $this->assertNotNull($story->fresh()->last_monitored_at);

        // story_topics pivot row exists for at least one topic.
        $this->assertGreaterThan(0, DB::table('story_topics')->where('story_id', $story->id)->count());

        // A story_updates row was created with the verdict's update_text.
        $this->assertGreaterThan(0, DB::table('story_updates')->where('story_id', $story->id)->count());
        $update = DB::table('story_updates')->where('story_id', $story->id)->first();
        $this->assertSame('Parliament passes the election reform bill in a historic vote.', $update->content);
        $this->assertNotNull($update->event_at);
    }

    /**
     * Judge returns urgency_adjustment='concluded' → story is concluded.
     */
    public function test_monitor_concludes_story_when_judge_says_concluded(): void
    {
        $parent = Category::create(['name' => 'National', 'slug' => 'national', 'display_order' => 1]);
        Category::create([
            'name' => 'Politics & Governance',
            'slug' => 'politics-governance',
            'parent_id' => $parent->id,
            'display_order' => 1,
        ]);

        $story = Story::factory()->live()->create([
            'search_query' => 'India election results 2026',
        ]);

        Http::fake([
            'news.google.com/*' => Http::response($this->googleRssXml(), 200, ['Content-Type' => 'application/rss+xml']),
            'api.gdeltproject.org/*' => Http::response([
                'articles' => [
                    [
                        'title' => 'Election results finalized, winner declared',
                        'url' => 'https://example.com/gdelt-final',
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

        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('judgeUpdateBatch')
            ->once()
            ->andReturnUsing(function ($storyArg, $candidates, $lastUpdate) {
                $verdicts = [];
                foreach ($candidates as $candidate) {
                    $verdicts[$candidate['topic_signature']] = [
                        'topic_signature' => $candidate['topic_signature'],
                        'is_new_development' => false,
                        'urgency_adjustment' => 'concluded',
                        'update_text' => 'Election results finalized with a winner declared.',
                        'reasoning' => 'The election has concluded with a winner declared.',
                    ];
                }

                return $verdicts;
            });

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:monitor-stories')->assertSuccessful();

        $storyFresh = $story->fresh();
        $this->assertSame('concluded', $storyFresh->urgency);
        $this->assertNotNull($storyFresh->concluded_at);

        // No generation dispatched for concluded story with no new developments.
        Queue::assertNotPushed(GenerateArticle::class);
    }

    /**
     * Auto-conclude after empty_cycles threshold when last update is stale.
     */
    public function test_auto_conclude_after_empty_cycles_threshold(): void
    {
        config(['news-engine.live_stories.auto_conclude_after_empty_cycles' => 2]);
        config(['news-engine.live_stories.monitor_interval_minutes' => 10]);

        $parent = Category::create(['name' => 'National', 'slug' => 'national', 'display_order' => 1]);
        Category::create([
            'name' => 'Politics & Governance',
            'slug' => 'politics-governance',
            'parent_id' => $parent->id,
            'display_order' => 1,
        ]);

        // Story with last article published >20 minutes ago (stale for 2*10=20 min threshold).
        $story = Story::factory()->create([
            'search_query' => 'India flood rescue operations',
            'empty_cycles' => 1, // One more empty cycle will hit threshold=2
        ]);

        // Link an old article to the story.
        $topic = NewsTopic::create([
            'category' => 'politics-governance',
            'topic_name' => 'India flood rescue',
            'topic_signature' => 'sig-flood-'.uniqid(),
            'source_count' => 1,
            'generation_status' => 'completed',
        ]);

        NewsArticle::create([
            'topic_id' => $topic->id,
            'story_id' => $story->id,
            'title' => 'Rescue operations continue in flooded regions',
            'content' => '<p>Body</p>',
            'status' => 'published',
            'published_at' => now()->subMinutes(30), // Stale: older than 2*10=20 min
        ]);

        Http::fake([
            'news.google.com/*' => Http::response($this->googleRssXml(), 200, ['Content-Type' => 'application/rss+xml']),
            'api.gdeltproject.org/*' => Http::response(['articles' => []], 200, ['Content-Type' => 'application/json']),
            'api.search.brave.com/*' => Http::response(['grounding' => ['sources' => []]], 200, ['Content-Type' => 'application/json']),
        ]);

        Cache::flush();
        Queue::fake();

        // Judge returns no new developments (empty cycle).
        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('judgeUpdateBatch')
            ->once()
            ->andReturn([]);

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:monitor-stories')->assertSuccessful();

        $storyFresh = $story->fresh();
        $this->assertSame('concluded', $storyFresh->urgency, 'Story should auto-conclude after empty_cycles threshold with stale last update.');
        $this->assertNotNull($storyFresh->concluded_at);
    }

    /**
     * Regression: when the story's candidates fuzzy-match an hourly-discovered
     * topic (same event covered by the hourly cron), the monitor must persist a
     * NEW story-namespaced topic row (not redirect to the hourly row) so the
     * pivot + generation both resolve. Previously this silently no-oped.
     */
    public function test_monitor_persists_story_namespaced_topic_when_fuzzy_duplicate_exists(): void
    {
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

        // An hourly-discovered topic for the SAME event, created 1h ago, with
        // core tokens that strongly overlap what the monitor will discover.
        NewsTopic::create([
            'category' => 'politics-governance',
            'topic_name' => 'Parliament passes election reform bill',
            'topic_signature' => sha1('india|parliament election reform bill vote'),
            'core_tokens' => json_encode(['parliament', 'election', 'reform', 'bill', 'vote', 'landmark']),
            'source_count' => 2,
            'generation_status' => 'generated',
            'created_at' => now()->subHour(),
        ]);

        $topicsBefore = NewsTopic::count();

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
                    [
                        'title' => 'Opposition party challenges election reform in Supreme Court',
                        'url' => 'https://example.com/gdelt-challenge',
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

        // A NEW story-namespaced topic row was created (fuzzy match did not
        // redirect the monitor to the hourly row).
        $this->assertSame($topicsBefore + 1, NewsTopic::count());

        // Pivot row exists with a non-null topic_id pointing at the new row.
        $pivot = DB::table('story_topics')->where('story_id', $story->id)->first();
        $this->assertNotNull($pivot, 'Pivot row must exist.');
        $this->assertNotNull($pivot->topic_id, 'Pivot topic_id must not be null (the old silent no-op).');

        $linkedTopic = NewsTopic::find($pivot->topic_id);
        $this->assertNotNull($linkedTopic);
        $this->assertNotSame(sha1('india|parliament election reform bill vote'), $linkedTopic->topic_signature, 'Must be a new namespaced row, not the hourly row.');
        $this->assertSame('pending', $linkedTopic->generation_status);

        // Generation dispatched for the new namespaced signature.
        Queue::assertPushed(GenerateArticle::class, function ($job) use ($story) {
            return $job->storyId === $story->id;
        });
    }

    /**
     * Candidates judged "repetition" still link via pivot (provenance), but
     * produce no timeline entry and no generation dispatch.
     */
    public function test_repetition_judged_candidates_still_link_via_pivot(): void
    {
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

        Http::fake([
            'news.google.com/*' => Http::response($this->googleRssXml(), 200, ['Content-Type' => 'application/rss+xml']),
            'api.gdeltproject.org/*' => Http::response(['articles' => []], 200, ['Content-Type' => 'application/json']),
            'api.search.brave.com/*' => Http::response(['grounding' => ['sources' => []]], 200, ['Content-Type' => 'application/json']),
        ]);

        Cache::flush();
        Queue::fake();

        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('judgeUpdateBatch')
            ->once()
            ->andReturnUsing(function ($storyArg, $candidates, $lastUpdate) {
                $verdicts = [];
                foreach ($candidates as $candidate) {
                    $verdicts[$candidate['topic_signature']] = [
                        'topic_signature' => $candidate['topic_signature'],
                        'is_new_development' => false,
                        'urgency_adjustment' => 'keep',
                        'update_text' => 'Parliament held further debate on the reform bill.',
                        'reasoning' => 'Restatement of prior coverage.',
                    ];
                }

                return $verdicts;
            });

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:monitor-stories')->assertSuccessful();

        // Pivot linked despite the "repetition" verdict.
        $this->assertGreaterThan(0, DB::table('story_topics')->where('story_id', $story->id)->count());
        // But no timeline entry and no generation for repetition.
        $this->assertSame(0, DB::table('story_updates')->where('story_id', $story->id)->count());
        Queue::assertNotPushed(GenerateArticle::class);
    }

    /**
     * Supporting-article resilience: a linked topic whose generation failed is
     * retried by the next monitor cycle (status reset, retry_count bumped,
     * job re-dispatched with storyId).
     */
    public function test_failed_supporting_topics_get_retried(): void
    {
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

        // A linked topic from a previous cycle whose generation failed.
        $failedTopic = NewsTopic::create([
            'category' => 'politics-governance',
            'topic_name' => 'Election reform bill clears committee',
            'topic_signature' => sha1('story:'.$story->id.'|committee tokens'),
            'core_tokens' => json_encode(['election', 'reform', 'committee']),
            'source_count' => 1,
            'generation_status' => 'failed',
            'retry_count' => 0,
            'created_at' => now()->subHour(),
        ]);

        DB::table('story_topics')->insert([
            'story_id' => $story->id,
            'topic_id' => $failedTopic->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'news.google.com/*' => Http::response($this->googleRssXml(), 200, ['Content-Type' => 'application/rss+xml']),
            'api.gdeltproject.org/*' => Http::response(['articles' => []], 200, ['Content-Type' => 'application/json']),
            'api.search.brave.com/*' => Http::response(['grounding' => ['sources' => []]], 200, ['Content-Type' => 'application/json']),
        ]);

        Cache::flush();
        Queue::fake();

        // Judge: no new developments this cycle (retry path runs regardless).
        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('judgeUpdateBatch')
            ->once()
            ->andReturn([]);

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:monitor-stories')->assertSuccessful();

        $topicFresh = $failedTopic->fresh();
        $this->assertSame('pending', $topicFresh->generation_status);
        $this->assertSame(1, $topicFresh->retry_count);

        Queue::assertPushed(GenerateArticle::class, function ($job) use ($story, $failedTopic) {
            return $job->storyId === $story->id && $job->topicSignature === $failedTopic->topic_signature;
        });
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
