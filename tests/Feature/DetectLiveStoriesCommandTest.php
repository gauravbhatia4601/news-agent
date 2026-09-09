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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
