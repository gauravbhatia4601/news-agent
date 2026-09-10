<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsTopic;
use App\News\DTO\DiscoveredSource;
use App\News\DTO\DiscoveredTopic;
use App\News\Repositories\NewsTopicRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NewsTopicRepositoryFuzzyMatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Hourly discovery (non-story, forceUniqueSignature=false) with a drifted
     * headline (plural/possessive variant) must attach to the existing topic
     * row instead of creating a new one. This is the fix for the ~700
     * articles/day explosion: raw-token Jaccard missed these variants, but
     * HeadlineSimilarity::tokens() stems them into the same match.
     */
    public function test_drifted_headline_candidate_attaches_to_existing_topic(): void
    {
        // Existing topic row — the "baseline" headline.
        NewsTopic::create([
            'category' => 'national',
            'topic_name' => 'Supreme Court strikes down electoral bond scheme',
            'topic_signature' => 'sig-existing-'.uniqid(),
            'core_tokens' => json_encode(['supreme', 'court', 'electoral', 'bond', 'scheme']),
            'source_count' => 2,
            'generation_status' => 'generated',
            'created_at' => now()->subHour(),
        ]);

        $topicsBefore = NewsTopic::count();

        // Discovered candidate: plural/possessive drift — "Court's" → "court",
        // "bonds" → "bond", "schemes" → "scheme" after stemming.
        $candidate = new DiscoveredTopic(
            category: 'national',
            name: "Supreme Court's electoral bonds scheme struck down by judges",
            signature: 'sig-candidate-'.uniqid(),
            sources: [
                new DiscoveredSource(
                    sourceName: 'Reuters',
                    sourceUrl: 'https://reuters.com/example',
                    headline: "Supreme Court's electoral bonds scheme struck down",
                    summary: 'The court ruled the scheme unconstitutional.',
                    publishedAt: Carbon::now(),
                    signature: 'src-sig-'.uniqid(),
                ),
            ],
            coreTokens: ['supreme', 'court', 'electoral', 'bond', 'scheme', 'judge'],
        );

        $repository = app(NewsTopicRepository::class);
        $topicId = $repository->saveTopicWithSources($candidate, false);

        // No new topic row created — attached to the existing one.
        $this->assertSame($topicsBefore, NewsTopic::count(), 'Drifted headline should attach to the existing topic, not create a new row.');

        // The returned ID is the existing topic's ID (looked up by ID since
        // the repository updates topic_name on attach).
        $attached = NewsTopic::find($topicId);
        $this->assertNotNull($attached);
        $this->assertSame("Supreme Court's electoral bonds scheme struck down by judges", $attached->topic_name, 'topic_name should be updated to the candidate name on attach.');
    }

    /**
     * A genuinely distinct headline must still create a new topic row —
     * the fuzzy match must not over-attach.
     */
    public function test_distinct_headline_creates_new_topic(): void
    {
        NewsTopic::create([
            'category' => 'national',
            'topic_name' => 'Supreme Court strikes down electoral bond scheme',
            'topic_signature' => 'sig-existing-2-'.uniqid(),
            'core_tokens' => json_encode(['supreme', 'court', 'electoral', 'bond', 'scheme']),
            'source_count' => 2,
            'generation_status' => 'generated',
            'created_at' => now()->subHour(),
        ]);

        $topicsBefore = NewsTopic::count();

        $candidate = new DiscoveredTopic(
            category: 'technology',
            name: 'Tech giant unveils new AI accelerator chip for data centers',
            signature: 'sig-distinct-2-'.uniqid(),
            sources: [
                new DiscoveredSource(
                    sourceName: 'TechCrunch',
                    sourceUrl: 'https://techcrunch.com/example',
                    headline: 'Tech giant unveils new AI accelerator chip',
                    summary: 'A new chip for AI workloads.',
                    publishedAt: Carbon::now(),
                    signature: 'src-sig-2-'.uniqid(),
                ),
            ],
            coreTokens: ['tech', 'giant', 'ai', 'chip', 'accelerator', 'data', 'center'],
        );

        $repository = app(NewsTopicRepository::class);
        $repository->saveTopicWithSources($candidate, false);

        $this->assertSame($topicsBefore + 1, NewsTopic::count(), 'Distinct headline must create a new topic row.');
    }

    /**
     * forceUniqueSignature=true (story-namespaced) must NEVER fuzzy-match —
     * even when the headline is identical to an existing topic. This is the
     * guard that keeps story topic signatures stable.
     */
    public function test_force_unique_signature_bypasses_fuzzy_match(): void
    {
        NewsTopic::create([
            'category' => 'national',
            'topic_name' => 'Parliament passes election reform bill',
            'topic_signature' => 'sig-existing-3-'.uniqid(),
            'core_tokens' => json_encode(['parliament', 'election', 'reform', 'bill']),
            'source_count' => 2,
            'generation_status' => 'generated',
            'created_at' => now()->subHour(),
        ]);

        $topicsBefore = NewsTopic::count();

        $candidate = new DiscoveredTopic(
            category: 'national',
            name: 'Parliament passes election reform bill',
            signature: 'story:42|'.sha1('parliament election reform bill'),
            sources: [
                new DiscoveredSource(
                    sourceName: 'Reuters',
                    sourceUrl: 'https://reuters.com/example2',
                    headline: 'Parliament passes election reform bill',
                    summary: 'Reform passes.',
                    publishedAt: Carbon::now(),
                    signature: 'src-sig-3-'.uniqid(),
                ),
            ],
            coreTokens: ['parliament', 'election', 'reform', 'bill'],
        );

        $repository = app(NewsTopicRepository::class);
        $repository->saveTopicWithSources($candidate, true);

        $this->assertSame($topicsBefore + 1, NewsTopic::count(), 'forceUniqueSignature must always create a new row.');
    }
}
