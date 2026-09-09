<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\Story;
use App\Models\User;
use App\News\Services\MonitorLiveStoriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['is_admin' => true]);

        return $admin->createToken('admin-token', ['admin'])->plainTextToken;
    }

    public function test_admin_can_list_stories(): void
    {
        Story::factory()->live()->create(['title' => 'Live Crisis']);
        Story::factory()->concluded()->create(['title' => 'Old Event']);

        $response = $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/stories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'title', 'urgency', 'status']],
                'current_page',
                'total',
                'per_page',
            ]);
    }

    public function test_admin_can_filter_stories_by_urgency(): void
    {
        Story::factory()->live()->create(['title' => 'Live']);
        $concluded = Story::factory()->concluded()->create(['title' => 'Concluded']);

        $response = $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/stories?urgency=concluded');

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($concluded->id, $ids);
    }

    public function test_admin_can_search_stories_by_title(): void
    {
        Story::factory()->create(['title' => 'Unique Keyword Story']);
        Story::factory()->create(['title' => 'Other Story']);

        $response = $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/stories?search=Unique+Keyword');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_can_create_a_story(): void
    {
        $response = $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/stories', [
                'title' => 'Breaking Election Results',
                'search_query' => 'election results 2026',
                'urgency' => 'live',
                'description' => 'Live coverage of the election.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.urgency', 'live')
            ->assertJsonPath('data.status', 'admin-created');

        $this->assertDatabaseHas('stories', [
            'title' => 'Breaking Election Results',
            'status' => 'admin-created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'resource_type' => 'Story',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/stories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'search_query', 'urgency']);
    }

    public function test_admin_can_show_a_story_with_timeline(): void
    {
        $story = Story::factory()->live()->create();
        NewsArticle::factory()->create([
            'story_id' => $story->id,
            'published_at' => now()->subHour(),
        ]);

        $response = $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/stories/{$story->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $story->id)
            ->assertJsonStructure(['data' => ['timeline']]);
    }

    public function test_admin_can_update_a_story(): void
    {
        $story = Story::factory()->create(['title' => 'Original']);

        $response = $this->withToken($this->adminToken())
            ->putJson("/api/v1/admin/stories/{$story->id}", [
                'title' => 'Updated Title',
                'urgency' => 'ongoing',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('stories', ['id' => $story->id, 'title' => 'Updated Title']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'update', 'resource_type' => 'Story']);
    }

    public function test_admin_can_activate_a_concluded_story(): void
    {
        $story = Story::factory()->concluded()->create();

        $response = $this->withToken($this->adminToken())
            ->postJson("/api/v1/admin/stories/{$story->id}/activate");

        $response->assertStatus(200)
            ->assertJsonPath('data.urgency', 'developing')
            ->assertJsonPath('data.concluded_at', null);

        $this->assertDatabaseHas('audit_logs', ['action' => 'activate', 'resource_type' => 'Story']);
    }

    public function test_admin_can_conclude_a_story(): void
    {
        $story = Story::factory()->live()->create();

        $response = $this->withToken($this->adminToken())
            ->postJson("/api/v1/admin/stories/{$story->id}/conclude");

        $response->assertStatus(200)
            ->assertJsonPath('data.urgency', 'concluded');

        $this->assertNotNull($response->json('data.concluded_at'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'conclude', 'resource_type' => 'Story']);
    }

    public function test_admin_can_trigger_monitor_cycle(): void
    {
        $story = Story::factory()->live()->create();

        $this->mock(MonitorLiveStoriesService::class, function ($mock) {
            $mock->shouldReceive('runCycle')->once()->andReturn([
                'discovered' => 3,
                'judged_new' => 1,
                'dispatched' => 1,
                'concluded' => false,
            ]);
        });

        $response = $this->withToken($this->adminToken())
            ->postJson("/api/v1/admin/stories/{$story->id}/trigger-monitor");

        $response->assertStatus(200)
            ->assertJsonPath('data.discovered', 3)
            ->assertJsonPath('data.dispatched', 1);

        $this->assertDatabaseHas('audit_logs', ['action' => 'trigger_monitor', 'resource_type' => 'Story']);
    }

    public function test_admin_can_batch_conclude_stories(): void
    {
        $s1 = Story::factory()->live()->create();
        $s2 = Story::factory()->create();

        $response = $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/stories/batch', [
                'ids' => [$s1->id, $s2->id],
                'action' => 'conclude',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('stories', ['id' => $s1->id, 'urgency' => 'concluded']);
        $this->assertDatabaseHas('stories', ['id' => $s2->id, 'urgency' => 'concluded']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'batch_conclude', 'resource_type' => 'Story']);
    }

    public function test_admin_can_delete_story_and_unlinks_articles(): void
    {
        $story = Story::factory()->live()->create();
        $article = NewsArticle::factory()->create(['story_id' => $story->id]);

        $response = $this->withToken($this->adminToken())
            ->deleteJson("/api/v1/admin/stories/{$story->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('stories', ['id' => $story->id]);
        // Article survives, story_id cleared.
        $this->assertDatabaseHas('news_articles', ['id' => $article->id, 'story_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delete', 'resource_type' => 'Story']);
    }

    public function test_non_admin_cannot_access_admin_stories(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = $user->createToken('test', ['*'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/stories')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_admin_stories(): void
    {
        $this->getJson('/api/v1/admin/stories')->assertStatus(401);
    }
}
