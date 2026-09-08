<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_subscribe_with_valid_email(): void
    {
        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'test@example.com');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_subscribe_records_consent(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'test@example.com',
        ]);

        $subscriber = NewsletterSubscriber::where('email', 'test@example.com')->first();

        $this->assertNotNull($subscriber->consent_at);
        $this->assertNotNull($subscriber->consent_text);
        $this->assertNotNull($subscriber->ip_address);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_unsubscribe(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create([
            'email' => 'test@example.com',
            'unsubscribed_at' => null,
        ]);

        $response = $this->postJson('/api/v1/newsletter/unsubscribe', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);

        $subscriber->refresh();
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    public function test_resubscribe_updates_existing_record(): void
    {
        NewsletterSubscriber::factory()->create([
            'email' => 'test@example.com',
            'unsubscribed_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);

        $subscriber = NewsletterSubscriber::where('email', 'test@example.com')->first();
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertNotNull($subscriber->consent_at);
    }
}
