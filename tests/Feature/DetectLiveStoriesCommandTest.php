<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Services\LiveStoryAgentService;
use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class DetectLiveStoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Detection creates a Story from a live-event topic, links the pivot,
     * retcons an existing article, and dedupes on the second run.
     */
    public function test_detect_creates_story_links_pivot_and_retcons_article(): void
    {
        // Seed a category.
        $parent = Category::create(['name' => 'National', 'slug' => 'national', 'display_order' => 1]);
        $category = Category::create([
            'name' => 'Politics & Governance',
            'slug' => 'politics-governance',
            'parent_id' => $parent->id,
            'display_order' => 1,
        ]);

        // Seed a topic created 1h ago with sources.
        $topic = NewsTopic::create([
            'category' => 'politics-governance',
            'topic_name' => 'India parliament election reform bill',
            'topic_signature' => 'sig-test-'.uniqid(),
            'source_count' => 2,
            'generation_status' => 'completed',
            'category_id' => $category->id,
            'created_at' => now()->subHour(),
        ]);

        DB::table('news_topic_sources')->insert([
            'topic_id' => $topic->id,
            'source_name' => 'Reuters',
            'source_url' => 'https://reuters.com/election-reform',
            'source_url_hash' => sha1('https://reuters.com/election-reform'),
            'headline' => 'Parliament passes landmark election reform bill',
            'summary' => 'Parliament passed the landmark election reform bill today.',
            'published_at' => now()->subMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed an article linked to the topic (pre-existing).
        $article = NewsArticle::create([
            'topic_id' => $topic->id,
            'title' => 'Election reform bill passes parliament',
            'content' => '<p>Article body</p>',
            'status' => 'published',
            'published_at' => now()->subMinutes(20),
        ]);

        // Mock the agent service: one live event, one non-live.
        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('detectLiveStories')
            ->once()
            ->andReturn([
                [
                    'topic_signature' => $topic->topic_signature,
                    'is_live_event' => true,
                    'suggested_title' => 'India Election Reform Bill',
                    'search_query' => 'India election reform bill parliament',
                    'urgency' => 'live',
                    'reasoning' => 'Ongoing legislative process with expected future developments.',
                ],
            ]);

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:detect-live-stories')->assertSuccessful();

        // Story created with correct attributes.
        $this->assertDatabaseHas('stories', [
            'title' => 'India Election Reform Bill',
            'search_query' => 'India election reform bill parliament',
            'urgency' => 'live',
            'status' => 'auto-detected',
        ]);

        $story = Story::where('title', 'India Election Reform Bill')->first();
        $this->assertNotNull($story);

        // story_topics pivot row exists.
        $this->assertDatabaseHas('story_topics', [
            'story_id' => $story->id,
            'topic_id' => $topic->id,
        ]);

        // Existing article retconned (story_id set).
        $this->assertSame($story->id, $article->fresh()->story_id);

        // Dedupe: second run should not create a duplicate story.
        $mock2 = Mockery::mock(LiveStoryAgentService::class);
        $mock2->shouldReceive('detectLiveStories')
            ->once()
            ->andReturn([
                [
                    'topic_signature' => $topic->topic_signature,
                    'is_live_event' => true,
                    'suggested_title' => 'India Election Reform Bill',
                    'search_query' => 'India election reform bill parliament',
                    'urgency' => 'live',
                    'reasoning' => 'Same event.',
                ],
            ]);
        $this->app->instance(LiveStoryAgentService::class, $mock2);

        $this->artisan('news:detect-live-stories')->assertSuccessful();

        $this->assertSame(1, Story::where('title', 'India Election Reform Bill')->count(), 'No duplicate story should be created.');
    }

    /**
     * Two same-event candidates in one batch (near-identical suggested titles)
     * must produce only ONE story — the second candidate must see the first
     * via the in-batch dedupe (root cause of prod dupe stories 212/213, both
     * created in the same second).
     */
    public function test_detect_dedupes_two_same_event_candidates_in_one_batch(): void
    {
        $parent = Category::create(['name' => 'World', 'slug' => 'world', 'display_order' => 1]);
        $category = Category::create([
            'name' => 'Trade & Diplomacy',
            'slug' => 'trade-diplomacy',
            'parent_id' => $parent->id,
            'display_order' => 1,
        ]);

        // Two distinct topics (different signatures) about the same event.
        $topicA = NewsTopic::create([
            'category' => 'trade-diplomacy',
            'topic_name' => 'EU-India FTA progresses toward signing',
            'topic_signature' => 'sig-fta-a-'.uniqid(),
            'source_count' => 2,
            'generation_status' => 'completed',
            'category_id' => $category->id,
            'created_at' => now()->subHour(),
        ]);
        $topicB = NewsTopic::create([
            'category' => 'trade-diplomacy',
            'topic_name' => 'EU-India FTA enters final approval stage',
            'topic_signature' => 'sig-fta-b-'.uniqid(),
            'source_count' => 2,
            'generation_status' => 'completed',
            'category_id' => $category->id,
            'created_at' => now()->subHour(),
        ]);

        foreach ([$topicA, $topicB] as $topic) {
            DB::table('news_topic_sources')->insert([
                'topic_id' => $topic->id,
                'source_name' => 'Reuters',
                'source_url' => 'https://reuters.com/'.uniqid(),
                'source_url_hash' => sha1('https://reuters.com/'.uniqid()),
                'headline' => 'EU-India FTA nears final signing',
                'summary' => 'EU and India close in on a free trade agreement.',
                'published_at' => now()->subMinutes(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // The LLM returns two same-event candidates in one batch with
        // near-identical suggested titles (stemmed overlap >= 0.5) and
        // overlapping search queries. Without the in-batch dedupe, both
        // pass before either story exists.
        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('detectLiveStories')
            ->once()
            ->andReturn([
                [
                    'topic_signature' => $topicA->topic_signature,
                    'is_live_event' => true,
                    'suggested_title' => 'EU-India Free Trade Agreement Nears Final Signing',
                    'search_query' => 'EU India free trade agreement signing',
                    'urgency' => 'live',
                    'reasoning' => 'Ongoing trade negotiations.',
                ],
                [
                    'topic_signature' => $topicB->topic_signature,
                    'is_live_event' => true,
                    'suggested_title' => 'EU-India Free Trade Agreement Reaches Final Approval',
                    'search_query' => 'EU India free trade agreement final approval',
                    'urgency' => 'live',
                    'reasoning' => 'Same event, different framing.',
                ],
            ]);

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:detect-live-stories')->assertSuccessful();

        // Exactly one story — the second candidate was caught by the in-batch
        // title/query dedupe (HeadlineSimilarity overlap on the shared
        // "EU-India FTA" stem set).
        $this->assertSame(1, Story::count(), 'Two same-event candidates in one batch must produce only one story.');
    }

    /**
     * Non-live-event topics do not create stories.
     */
    public function test_detect_skips_non_live_events(): void
    {
        $topic = NewsTopic::create([
            'category' => 'lifestyle',
            'topic_name' => 'Best restaurants in Mumbai',
            'topic_signature' => 'sig-lifestyle-'.uniqid(),
            'source_count' => 1,
            'generation_status' => 'pending',
            'created_at' => now()->subHour(),
        ]);

        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('detectLiveStories')
            ->once()
            ->andReturn([
                [
                    'topic_signature' => $topic->topic_signature,
                    'is_live_event' => false,
                    'suggested_title' => 'Best Mumbai Restaurants',
                    'search_query' => 'Mumbai restaurants',
                    'urgency' => 'developing',
                    'reasoning' => 'Evergreen listicle, not a live event.',
                ],
            ]);

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:detect-live-stories')->assertSuccessful();

        $this->assertSame(0, Story::count(), 'No story should be created for non-live events.');
    }

    /**
     * Title-based dedupe: a candidate whose suggested_title is similar (stemmed
     * overlap) to an existing active story's title is skipped even when the
     * search_query Jaccard misses it (the LLM rephrased the query).
     */
    public function test_detect_skips_candidate_with_similar_title_to_existing_story(): void
    {
        // Existing active story — the LLM rephrased the search_query, so
        // search_query Jaccard will NOT catch the duplicate.
        Story::create([
            'slug' => 'trumps-5000-midterm-payout-promise-controversy',
            'title' => "Trump's \$5,000 Midterm Payout Promise Controversy",
            'search_query' => 'Trump dividend payout midterm controversy backlash',
            'urgency' => 'live',
            'status' => 'auto-detected',
            'started_at' => now(),
        ]);

        // A topic with a totally different signature.
        $topic = NewsTopic::create([
            'category' => 'politics-governance',
            'topic_name' => 'Trump promises $5,000 dividend for midterm wins',
            'topic_signature' => 'sig-title-dedupe-'.uniqid(),
            'source_count' => 1,
            'generation_status' => 'completed',
            'created_at' => now()->subHour(),
        ]);

        DB::table('news_topic_sources')->insert([
            'topic_id' => $topic->id,
            'source_name' => 'Reuters',
            'source_url' => 'https://reuters.com/trump-dividend',
            'source_url_hash' => sha1('https://reuters.com/trump-dividend'),
            'headline' => 'Trump Promises $5,000 Dividend for Midterm Wins',
            'summary' => 'Trump promises a $5,000 dividend for midterm wins.',
            'published_at' => now()->subMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The LLM returns a suggested_title that is similar to the existing
        // story's title but a search_query that is NOT similar (different
        // wording → Jaccard < 0.6).
        $mock = Mockery::mock(LiveStoryAgentService::class);
        $mock->shouldReceive('detectLiveStories')
            ->once()
            ->andReturn([
                [
                    'topic_signature' => $topic->topic_signature,
                    'is_live_event' => true,
                    'suggested_title' => 'Trump Promises $5,000 Dividend for Midterm Wins',
                    'search_query' => 'Trump GOP election dividend promise pledge',
                    'urgency' => 'live',
                    'reasoning' => 'Ongoing story.',
                ],
            ]);

        $this->app->instance(LiveStoryAgentService::class, $mock);

        $this->artisan('news:detect-live-stories')->assertSuccessful();

        // No new story created — the existing one stays the only story.
        $this->assertSame(1, Story::count(), 'Title-similar candidate should not create a duplicate story.');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
