<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminRemoveArticleImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_article_image(): void
    {
        Storage::fake('public');

        // Store a fake image so the delete path is exercised.
        Storage::disk('public')->put('news-images/test.jpg', 'fake-content');

        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $topic = NewsTopic::create([
            'category' => 'tech',
            'topic_name' => 'Image topic',
            'topic_signature' => 'sig-img-'.uniqid(),
            'generation_status' => 'completed',
            'source_count' => 1,
        ]);

        $article = NewsArticle::create([
            'topic_id' => $topic->id,
            'title' => 'Article with image',
            'content' => 'body',
            'status' => 'published',
            'image_url' => '/storage/news-images/test.jpg',
            'thumbnail_url' => '/storage/news-images/test.jpg',
            'metadata' => ['image_origin' => 'source', 'author' => 'AI News Desk'],
        ]);

        $response = $this->withToken($token)
            ->deleteJson('/api/v1/admin/articles/'.$article->id.'/image');

        $response->assertStatus(200);
        $response->assertJsonPath('data.image_url', null);
        $response->assertJsonPath('data.thumbnail_url', null);
        $response->assertJsonPath('data.metadata.image_origin', null);

        // File deleted from disk.
        Storage::disk('public')->assertMissing('news-images/test.jpg');

        // Database fields nulled.
        $this->assertDatabaseHas('news_articles', [
            'id' => $article->id,
            'image_url' => null,
            'thumbnail_url' => null,
        ]);

        // Audit log entry written.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'remove_image',
            'resource_type' => 'Article',
            'resource_id' => $article->id,
        ]);
    }

    public function test_remove_image_returns_404_for_missing_article(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $response = $this->withToken($token)
            ->deleteJson('/api/v1/admin/articles/999999/image');

        $response->assertStatus(404);
    }

    public function test_unauthorized_access_rejected(): void
    {
        $topic = NewsTopic::create([
            'category' => 'tech',
            'topic_name' => 'Auth topic',
            'topic_signature' => 'sig-auth-'.uniqid(),
            'generation_status' => 'completed',
            'source_count' => 1,
        ]);

        $article = NewsArticle::create([
            'topic_id' => $topic->id,
            'title' => 'Article',
            'content' => 'body',
            'status' => 'published',
            'image_url' => '/storage/news-images/test.jpg',
        ]);

        // No token at all.
        $response = $this->deleteJson('/api/v1/admin/articles/'.$article->id.'/image');
        $response->assertStatus(401);
    }

    public function test_non_admin_user_rejected(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = $user->createToken('user-token', ['admin'])->plainTextToken;

        $topic = NewsTopic::create([
            'category' => 'tech',
            'topic_name' => 'Non-admin topic',
            'topic_signature' => 'sig-nonadmin-'.uniqid(),
            'generation_status' => 'completed',
            'source_count' => 1,
        ]);

        $article = NewsArticle::create([
            'topic_id' => $topic->id,
            'title' => 'Article',
            'content' => 'body',
            'status' => 'published',
            'image_url' => '/storage/news-images/test.jpg',
        ]);

        $response = $this->withToken($token)
            ->deleteJson('/api/v1/admin/articles/'.$article->id.'/image');

        $response->assertStatus(403);
    }
}
