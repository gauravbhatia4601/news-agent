<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiModelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiModelsSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A slice of the OpenRouter feed shape: base models, :batch/:free twins,
     * a bad-pricing row, a missing-pricing row, and a non-curated provider.
     */
    private function feed(array $overrides = []): array
    {
        $rows = [
            [
                'id' => 'anthropic/claude-sonnet-5',
                'name' => 'Claude Sonnet 5',
                'context_length' => 1000000,
                'pricing' => ['prompt' => '0.000002', 'completion' => '0.00001'],
                'architecture' => ['modality' => 'text+image+file->text'],
            ],
            [
                'id' => 'anthropic/claude-sonnet-5:batch',
                'name' => 'Claude Sonnet 5 (batch)',
                'context_length' => 1000000,
                'pricing' => ['prompt' => '0.000001', 'completion' => '0.000005'],
                'architecture' => ['modality' => 'text+image+file->text'],
            ],
            [
                'id' => 'openai/gpt-5.5',
                'name' => 'OpenAI GPT 5.5',
                'context_length' => 1050000,
                'pricing' => ['prompt' => '0.000005', 'completion' => '0.000025'],
                'architecture' => ['modality' => 'text+image+file->text'],
            ],
            [
                'id' => 'deepseek/deepseek-v4-flash:free',
                'name' => 'DeepSeek V4 Flash (free)',
                'context_length' => 131072,
                'pricing' => ['prompt' => '0', 'completion' => '0'],
                'architecture' => ['modality' => 'text->text'],
            ],
            [
                // Bad pricing (non-numeric) — skipped, not stored.
                'id' => 'weird/model-bad-pricing',
                'name' => 'Weird Model',
                'context_length' => 4096,
                'pricing' => ['prompt' => 'ask', 'completion' => null],
                'architecture' => ['modality' => 'text->text'],
            ],
            [
                // Missing pricing entirely — skipped, not stored.
                'id' => 'weird/model-no-pricing',
                'name' => 'Weird Model 2',
                'context_length' => 4096,
                'architecture' => ['modality' => 'text->text'],
            ],
        ];

        return array_map(fn (array $row) => array_merge($row, $overrides), $rows);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function fakeFeed(array $rows): void
    {
        Http::fake([
            'openrouter.ai/api/v1/models' => Http::response(['data' => $rows], 200, ['Content-Type' => 'application/json']),
        ]);
    }

    public function test_sync_creates_providers_and_models_relationally(): void
    {
        $this->fakeFeed($this->feed());

        $this->artisan('ai:sync-models')->assertSuccessful();

        $this->assertSame(3, AiModelProvider::count()); // anthropic, openai, deepseek

        $anthropic = AiModelProvider::where('slug', 'anthropic')->first();
        $this->assertNotNull($anthropic);

        // Bad/missing pricing rows skipped; :free stored as a free Variant.
        $this->assertSame(4, AiModel::count());

        $sonnet = AiModel::where('feed_id', 'anthropic/claude-sonnet-5')->first();
        $this->assertNotNull($sonnet);
        $this->assertSame($anthropic->id, $sonnet->ai_model_provider_id);
        $this->assertSame('Claude Sonnet 5', $sonnet->name);
        $this->assertSame(1000000, $sonnet->context_length);
        $this->assertSame('2.000000', (string) $sonnet->input_price_per_million);
        $this->assertSame('10.000000', (string) $sonnet->output_price_per_million);
        $this->assertSame('base', $sonnet->variant);
        $this->assertTrue($sonnet->is_active);
        $this->assertNotNull($sonnet->last_seen_at);
    }

    public function test_sync_marks_variant_rows(): void
    {
        $this->fakeFeed($this->feed());

        $this->artisan('ai:sync-models')->assertSuccessful();

        $batch = AiModel::where('feed_id', 'anthropic/claude-sonnet-5:batch')->first();
        $this->assertNotNull($batch);
        $this->assertSame('batch', $batch->variant);

        $free = AiModel::where('feed_id', 'deepseek/deepseek-v4-flash:free')->first();
        $this->assertNotNull($free);
        $this->assertSame('free', $free->variant);
    }

    public function test_sync_rerun_upserts_without_duplicates(): void
    {
        $this->fakeFeed($this->feed());

        $this->artisan('ai:sync-models')->assertSuccessful();
        $this->artisan('ai:sync-models')->assertSuccessful();

        $this->assertSame(3, AiModelProvider::count());
        $this->assertSame(4, AiModel::count());
    }

    public function test_sync_deactivates_models_missing_from_feed(): void
    {
        // One fake, two responses in sequence: full feed, then the later feed
        // without openai/gpt-5.5. (Http::fake() stacks rather than replaces —
        // two fakeFeed calls would keep serving the FIRST feed.)
        Http::fakeSequence('openrouter.ai/api/v1/models')
            ->pushResponse(Http::response(['data' => $this->feed()], 200, ['Content-Type' => 'application/json']))
            ->pushResponse(Http::response([
                'data' => array_values(array_filter($this->feed(), fn (array $r) => $r['id'] !== 'openai/gpt-5.5')),
            ], 200, ['Content-Type' => 'application/json']))
            ->whenEmpty(Http::response(['data' => []], 200, ['Content-Type' => 'application/json']));

        $this->artisan('ai:sync-models')->assertSuccessful();
        $this->artisan('ai:sync-models')->assertSuccessful();

        $gone = AiModel::where('feed_id', 'openai/gpt-5.5')->first();
        $this->assertNotNull($gone, 'Row must remain — nothing is ever deleted');
        $this->assertFalse($gone->is_active);
        $this->assertNotNull($gone->last_seen_at);

        $still = AiModel::where('feed_id', 'anthropic/claude-sonnet-5')->first();
        $this->assertTrue($still->is_active);
    }

    public function test_sync_deactivates_only_this_source_models(): void
    {
        // A model synced from another source (huggingface later) is not deactivated.
        $provider = AiModelProvider::create(['slug' => 'openai', 'name' => 'OpenAI']);
        AiModel::create([
            'ai_model_provider_id' => $provider->id,
            'feed_id' => 'openrouter:openai/gpt-4-from-other-feed',
            'name' => 'GPT 4 from other feed',
            'source' => 'other-feed',
            'variant' => 'base',
            'is_active' => true,
        ]);

        $this->fakeFeed($this->feed());
        $this->artisan('ai:sync-models')->assertSuccessful();

        $this->assertTrue(AiModel::where('source', 'other-feed')->first()->is_active);
    }

    public function test_sync_records_last_synced_at(): void
    {
        $this->fakeFeed($this->feed());

        $this->artisan('ai:sync-models')->assertSuccessful();

        $state = DB::table('ai_sync_state')->where('source', 'openrouter')->first();
        $this->assertNotNull($state);
        $this->assertNotNull($state->synced_at);
    }

    public function test_sync_survives_feed_failure(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/models' => Http::response('gateway timeout', 504),
        ]);

        $this->artisan('ai:sync-models')->assertFailed();

        $this->assertSame(0, AiModel::count());
    }

    public function test_sync_skips_rows_without_slash_id(): void
    {
        $rows = $this->feed();
        $rows[] = ['id' => 'no-slash-here', 'name' => 'Broken', 'context_length' => 8,
            'pricing' => ['prompt' => '0.000001', 'completion' => '0.000001'],
            'architecture' => ['modality' => 'text->text']];

        $this->fakeFeed($rows);
        $this->artisan('ai:sync-models')->assertSuccessful();

        $this->assertSame(0, AiModelProvider::where('slug', 'no-slash-here')->count());
    }
}
