<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedAndHealthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FIX 7 — feed.xml returns 200 and valid XML with an escaped slug.
     */
    public function test_feed_xml_returns_valid_xml_with_escaped_slug(): void
    {
        $topic = NewsTopic::factory()->create();

        $article = NewsArticle::factory()->create([
            'topic_id' => $topic->id,
            'title' => 'Test Article <script>alert(1)</script>',
            'slug' => 'test-slug-with-"quotes"-and-&-ampersands',
            'status' => 'published',
        ]);

        $response = $this->get('/feed.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/rss+xml');

        $body = $response->getContent();
        $this->assertNotEmpty($body);
        $this->assertStringContainsString('<rss', $body);
        $this->assertStringContainsString('<channel>', $body);

        // The slug must be HTML-escaped so it can't break the XML structure.
        $this->assertStringContainsString(htmlspecialchars($article->slug), $body);
        // Title angle brackets must be escaped (no raw <script> tag in XML).
        $this->assertStringNotContainsString('<script>', $body);
    }

    /**
     * FIX 8 — unauthenticated health request is 401.
     */
    public function test_health_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/admin/health');

        $response->assertStatus(401);
    }

    /**
     * FIX 8 — authenticated health request does not leak raw exception text.
     */
    public function test_health_endpoint_does_not_leak_raw_exception_text(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/health');

        // In testing, CACHE_STORE=array so redis check will fail.
        // The response should use 'unavailable' not 'error: <raw message>'.
        $body = $response->json();

        foreach ($body['checks'] ?? [] as $key => $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString('error:', $value, "Check '{$key}' leaked raw exception: {$value}");
            }
        }
    }
}
