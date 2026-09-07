<?php

namespace Tests\Feature;

use App\Jobs\GenerateArticle;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Models\User;
use App\News\Services\NewsDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AdminFixesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FIX 1 — admin trigger with scope=global passes global to the discovery service.
     */
    public function test_trigger_passes_global_scope_to_discovery_service(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $mock = Mockery::mock(NewsDiscoveryService::class);
        $mock->shouldReceive('discover')
            ->once()
            ->withArgs(function ($locations, $limit, $freshHours, $sourcesPerTopic, $scope): bool {
                return $scope === 'global';
            })
            ->andReturn([]);
        $this->app->instance(NewsDiscoveryService::class, $mock);

        Queue::fake();

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/discovery/trigger', [
                'scope' => 'global',
                'queue' => false,
            ]);

        $response->assertStatus(200);
        Queue::assertNotPushed(GenerateArticle::class);
    }

    public function test_trigger_rejects_invalid_scope(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/discovery/trigger', [
                'scope' => 'national',
                'queue' => false,
            ]);

        $response->assertStatus(422);
    }

    /**
     * FIX 2 — regenerate dispatches the job WITHOUT deleting the article.
     */
    public function test_regenerate_dispatches_job_without_deleting_article(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $topic = NewsTopic::create([
            'category' => 'tech',
            'topic_name' => 'Test topic',
            'topic_signature' => 'sig-'.uniqid(),
            'generation_status' => 'completed',
            'source_count' => 1,
        ]);

        $article = NewsArticle::create([
            'topic_id' => $topic->id,
            'title' => 'Original article',
            'content' => 'body',
            'status' => 'published',
        ]);

        Queue::fake();

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/articles/'.$article->id.'/regenerate');

        $response->assertStatus(200);
        Queue::assertPushed(GenerateArticle::class);

        // Article must still exist immediately after the call.
        $this->assertDatabaseHas('news_articles', [
            'id' => $article->id,
            'title' => 'Original article',
        ]);

        // Topic status transition still applied.
        $this->assertSame('pending', $topic->fresh()->generation_status);
    }

    /**
     * FIX 2 — batch regenerate branch also keeps articles intact.
     */
    public function test_batch_regenerate_keeps_articles_intact(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $topic = NewsTopic::create([
            'category' => 'tech',
            'topic_name' => 'Batch topic',
            'topic_signature' => 'sig-batch-'.uniqid(),
            'generation_status' => 'completed',
            'source_count' => 1,
        ]);

        $article = NewsArticle::create([
            'topic_id' => $topic->id,
            'title' => 'Batch article',
            'content' => 'body',
            'status' => 'published',
        ]);

        Queue::fake();

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/articles/batch', [
                'ids' => [$article->id],
                'action' => 'regenerate',
            ]);

        $response->assertStatus(200);
        Queue::assertPushed(GenerateArticle::class);

        $this->assertDatabaseHas('news_articles', [
            'id' => $article->id,
            'title' => 'Batch article',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
