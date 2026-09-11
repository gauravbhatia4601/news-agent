<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateArticle;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JanitorTopicsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Stuck 'generating' topics older than 30 min with no article are reset
     * to pending and dispatched. Recent 'generating' topics are untouched.
     */
    public function test_stuck_generating_topics_are_recovered(): void
    {
        $stuck = NewsTopic::factory()->create([
            'generation_status' => 'generating',
            'topic_signature' => 'sig-stuck-'.uniqid(),
            'updated_at' => now()->subMinutes(45),
        ]);

        $recent = NewsTopic::factory()->create([
            'generation_status' => 'generating',
            'topic_signature' => 'sig-recent-'.uniqid(),
            'updated_at' => now()->subMinutes(10),
        ]);

        Queue::fake();

        $this->artisan('news:janitor-topics')->assertSuccessful();

        $this->assertSame('pending', $stuck->fresh()->generation_status, 'Stuck generating topic must be reset to pending.');
        $this->assertSame('generating', $recent->fresh()->generation_status, 'Recent generating topic must be untouched.');

        Queue::assertPushed(GenerateArticle::class, 1);
        Queue::assertPushed(GenerateArticle::class, fn ($job) => $job->topicSignature === $stuck->topic_signature);
    }

    /**
     * A stuck 'generating' topic that already has an article is NOT reset
     * — the article means generation succeeded, the flag is just stale.
     */
    public function test_stuck_generating_with_article_is_not_recovered(): void
    {
        $topic = NewsTopic::factory()->create([
            'generation_status' => 'generating',
            'topic_signature' => 'sig-with-article-'.uniqid(),
            'updated_at' => now()->subMinutes(45),
        ]);

        NewsArticle::factory()->create([
            'topic_id' => $topic->id,
        ]);

        Queue::fake();

        $this->artisan('news:janitor-topics')->assertSuccessful();

        $this->assertSame('generating', $topic->fresh()->generation_status, 'Topic with article must not be reset by janitor.');
        Queue::assertNotPushed(GenerateArticle::class);
    }

    /**
     * Stale 'pending' topics older than 2h with no article are dispatched.
     */
    public function test_stale_pending_topics_are_redispatched(): void
    {
        $stale = NewsTopic::factory()->create([
            'generation_status' => 'pending',
            'topic_signature' => 'sig-stale-'.uniqid(),
            'updated_at' => now()->subHours(3),
        ]);

        $fresh = NewsTopic::factory()->create([
            'generation_status' => 'pending',
            'topic_signature' => 'sig-fresh-'.uniqid(),
            'updated_at' => now()->subMinutes(30),
        ]);

        Queue::fake();

        $this->artisan('news:janitor-topics')->assertSuccessful();

        Queue::assertPushed(GenerateArticle::class, 1);
        Queue::assertPushed(GenerateArticle::class, fn ($job) => $job->topicSignature === $stale->topic_signature);
        $this->assertSame('pending', $stale->fresh()->generation_status, 'Stale pending topic stays pending (job dispatched, not status changed).');
        $this->assertSame('pending', $fresh->fresh()->generation_status, 'Fresh pending topic must be untouched.');
    }

    /**
     * duplicate_skipped topics are never touched by the janitor.
     */
    public function test_duplicate_skipped_topics_are_untouched(): void
    {
        $dup = NewsTopic::factory()->create([
            'generation_status' => 'duplicate_skipped',
            'topic_signature' => 'sig-dup-'.uniqid(),
            'updated_at' => now()->subHours(5),
        ]);

        Queue::fake();

        $this->artisan('news:janitor-topics')->assertSuccessful();

        $this->assertSame('duplicate_skipped', $dup->fresh()->generation_status);
        Queue::assertNotPushed(GenerateArticle::class);
    }

    /**
     * The cap of 10 is respected — only 10 stuck generating topics are
     * recovered per run, oldest first.
     */
    public function test_cap_is_respected_for_stuck_generating(): void
    {
        $topics = [];
        for ($i = 0; $i < 15; $i++) {
            $topics[] = NewsTopic::factory()->create([
                'generation_status' => 'generating',
                'topic_signature' => "sig-cap-{$i}-".uniqid(),
                'updated_at' => now()->subMinutes(40 + $i),
            ]);
        }

        Queue::fake();

        $this->artisan('news:janitor-topics')->assertSuccessful();

        $recovered = NewsTopic::where('generation_status', 'pending')->count();
        $this->assertSame(10, $recovered, 'Exactly 10 topics should be recovered (cap).');

        Queue::assertPushed(GenerateArticle::class, 10);
    }

    /**
     * --backfill-duplicates: article-less 'failed' topics with retry_count>=5
     * are reclassified to duplicate_skipped. Topics WITH articles stay 'failed'.
     */
    public function test_backfill_duplicates_reclassifies_article_less_failed(): void
    {
        // Article-less failed with retry_count=5 → should be backfilled.
        $toBackfill = NewsTopic::factory()->create([
            'generation_status' => 'failed',
            'retry_count' => 5,
            'updated_at' => now()->subDay(),
        ]);

        // Failed with article → stays 'failed' (true failure).
        $withArticle = NewsTopic::factory()->create([
            'generation_status' => 'failed',
            'retry_count' => 5,
            'updated_at' => now()->subDay(),
        ]);
        NewsArticle::factory()->create(['topic_id' => $withArticle->id]);

        // Failed with retry_count < 5 → stays 'failed' (not a save-time suppression).
        $lowRetry = NewsTopic::factory()->create([
            'generation_status' => 'failed',
            'retry_count' => 2,
            'updated_at' => now()->subDay(),
        ]);

        Queue::fake();

        $this->artisan('news:janitor-topics --backfill-duplicates')->assertSuccessful();

        $this->assertSame('duplicate_skipped', $toBackfill->fresh()->generation_status, 'Article-less failed with retry_count>=5 must be backfilled.');
        $this->assertSame('failed', $withArticle->fresh()->generation_status, 'Failed topic with article must stay failed.');
        $this->assertSame('failed', $lowRetry->fresh()->generation_status, 'Failed topic with retry_count<5 must stay failed.');

        // Backfill does not dispatch jobs.
        Queue::assertNotPushed(GenerateArticle::class);
    }
}
