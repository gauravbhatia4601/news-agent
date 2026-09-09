<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\Story;
use App\Models\StoryUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryTimelineEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_returns_reverse_chron_paginated_updates(): void
    {
        $story = Story::factory()->live()->create(['started_at' => now()->subDay()]);

        $oldest = StoryUpdate::create([
            'story_id' => $story->id,
            'content' => 'Initial report of the event.',
            'event_at' => now()->subHours(5),
        ]);
        $middle = StoryUpdate::create([
            'story_id' => $story->id,
            'content' => 'Authorities issue a public statement.',
            'event_at' => now()->subHours(2),
        ]);
        $newest = StoryUpdate::create([
            'story_id' => $story->id,
            'content' => 'Resolution reached after negotiations.',
            'event_at' => now()->subHour(),
        ]);

        $response = $this->getJson("/api/v1/stories/{$story->slug}/timeline");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'content', 'event_at', 'source_name', 'source_url'],
                ],
                'meta' => ['current_page', 'last_page', 'total', 'per_page'],
                'links',
            ]);

        $ids = array_column($response->json('data'), 'id');

        // Reverse-chron: newest first.
        $this->assertSame([$newest->id, $middle->id, $oldest->id], $ids);
        $this->assertCount(3, $ids);
    }

    public function test_timeline_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/v1/stories/does-not-exist/timeline');

        $response->assertStatus(404);
    }

    public function test_articles_endpoint_returns_supporting_articles(): void
    {
        $story = Story::factory()->live()->create(['started_at' => now()->subDay()]);

        $oldest = NewsArticle::factory()->create([
            'story_id' => $story->id,
            'published_at' => now()->subHours(5),
        ]);
        $middle = NewsArticle::factory()->create([
            'story_id' => $story->id,
            'published_at' => now()->subHours(2),
        ]);
        $newest = NewsArticle::factory()->create([
            'story_id' => $story->id,
            'published_at' => now()->subHour(),
        ]);

        // Unrelated article — must NOT appear in this story's articles endpoint.
        NewsArticle::factory()->create(['published_at' => now()]);

        $response = $this->getJson("/api/v1/stories/{$story->slug}/articles");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'slug', 'title', 'published_at'],
                ],
                'meta' => ['current_page', 'last_page', 'total', 'per_page'],
                'links',
            ]);

        $ids = array_column($response->json('data'), 'id');

        $this->assertSame([$newest->id, $middle->id, $oldest->id], $ids);
        $this->assertCount(3, $ids);
    }

    public function test_articles_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v1/stories/does-not-exist/articles')->assertStatus(404);
    }

    public function test_stories_index_lists_active_stories(): void
    {
        $active = Story::factory()->live()->create(['started_at' => now()->subHour()]);
        $concluded = Story::factory()->concluded()->create(['started_at' => now()->subDays(2)]);

        $response = $this->getJson('/api/v1/stories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'slug', 'title', 'urgency', 'status', 'started_at', 'update_count'],
                ],
                'meta',
                'links',
            ]);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($concluded->id, $ids);
    }

    public function test_stories_index_urgency_filter_includes_concluded(): void
    {
        $live = Story::factory()->live()->create(['started_at' => now()->subHour()]);
        $concluded = Story::factory()->concluded()->create(['started_at' => now()->subDays(2)]);

        $response = $this->getJson('/api/v1/stories?urgency=concluded');

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($concluded->id, $ids);
        $this->assertNotContains($live->id, $ids);
    }

    public function test_stories_index_developing_filter(): void
    {
        $developing = Story::factory()->create(['started_at' => now()->subHour()]);
        $live = Story::factory()->live()->create(['started_at' => now()->subHour()]);

        $response = $this->getJson('/api/v1/stories?urgency=developing');

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($developing->id, $ids);
        $this->assertNotContains($live->id, $ids);
    }

    public function test_story_resource_exposes_update_count_and_latest_update(): void
    {
        $story = Story::factory()->live()->create();

        StoryUpdate::create([
            'story_id' => $story->id,
            'content' => 'First development in the story.',
            'event_at' => now()->subHour(),
        ]);
        StoryUpdate::create([
            'story_id' => $story->id,
            'content' => 'Second development unfolds.',
            'event_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/stories/{$story->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $story->id)
            ->assertJsonPath('data.update_count', 2)
            ->assertJsonPath('data.latest_update.content', 'Second development unfolds.')
            ->assertJsonStructure([
                'data' => [
                    'id', 'slug', 'title', 'urgency', 'update_count', 'article_count', 'latest_update',
                ],
            ]);
    }

    public function test_story_show_returns_the_story(): void
    {
        $story = Story::factory()->live()->create();

        $response = $this->getJson("/api/v1/stories/{$story->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $story->id)
            ->assertJsonPath('data.slug', $story->slug)
            ->assertJsonPath('data.urgency', 'live');
    }

    public function test_story_show_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v1/stories/nope')->assertStatus(404);
    }
}
