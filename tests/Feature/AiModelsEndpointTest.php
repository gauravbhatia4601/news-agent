<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiModelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModelsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_db_returns_empty_models_list(): void
    {
        $response = $this->getJson('/api/v1/ai/models');

        $response->assertOk()
            ->assertJson(['data' => ['synced_at' => null, 'models' => []]]);
    }

    public function test_returns_active_models_with_provider_and_featured_flag(): void
    {
        $anthropic = AiModelProvider::create(['slug' => 'anthropic', 'name' => 'Anthropic']);
        $openai = AiModelProvider::create(['slug' => 'openai', 'name' => 'OpenAI']);

        AiModel::create([
            'ai_model_provider_id' => $anthropic->id,
            'feed_id' => 'anthropic/claude-sonnet-5',
            'source' => 'openrouter',
            'name' => 'Claude Sonnet 5',
            'context_length' => 1000000,
            'input_price_per_million' => 2.0,
            'output_price_per_million' => 10.0,
            'modality' => 'text+image+file->text',
            'intelligence_index' => 52.7,
            'coding_index' => 81.6,
            'agentic_index' => null,
            'variant' => 'base',
            'is_active' => true,
        ]);
        AiModel::create([
            'ai_model_provider_id' => $openai->id,
            'feed_id' => 'openai/gpt-5.5',
            'source' => 'openrouter',
            'name' => 'OpenAI GPT 5.5',
            'context_length' => 1050000,
            'input_price_per_million' => 5.0,
            'output_price_per_million' => 25.0,
            'modality' => 'text+image+file->text',
            'variant' => 'base',
            'is_active' => true,
        ]);
        // Inactive + variant rows must not appear.
        AiModel::create([
            'ai_model_provider_id' => $openai->id,
            'feed_id' => 'openai/gpt-4-retired',
            'source' => 'openrouter',
            'name' => 'GPT 4 (retired)',
            'variant' => 'base',
            'is_active' => false,
        ]);
        AiModel::create([
            'ai_model_provider_id' => $anthropic->id,
            'feed_id' => 'anthropic/claude-sonnet-5:batch',
            'source' => 'openrouter',
            'name' => 'Claude Sonnet 5 (batch)',
            'variant' => 'batch',
            'is_active' => true,
        ]);

        config()->set('news-engine.ai_models.featured', ['anthropic/claude-sonnet-5']);

        $response = $this->getJson('/api/v1/ai/models');

        $models = $response->assertOk()->json('data.models');
        $this->assertCount(2, $models);

        $ids = array_column($models, 'feed_id');
        $this->assertContains('anthropic/claude-sonnet-5', $ids);
        $this->assertContains('openai/gpt-5.5', $ids);

        $sonnet = $models[0];
        $this->assertSame('anthropic/claude-sonnet-5', $sonnet['feed_id']);
        $this->assertSame('anthropic', $sonnet['provider']);
        $this->assertSame('Anthropic', $sonnet['provider_name']);
        $this->assertTrue($sonnet['featured']);
        $this->assertSame(1000000, $sonnet['context_length']);
        $this->assertSame(2.0, (float) $sonnet['input_price_per_million']);
        $this->assertSame(10.0, (float) $sonnet['output_price_per_million']);
        $this->assertSame(52.7, (float) $sonnet['intelligence_index']);
        $this->assertSame(81.6, (float) $sonnet['coding_index']);
        $this->assertNull($sonnet['agentic_index']);

        $gpt = array_values(array_filter($models, fn ($m) => $m['feed_id'] === 'openai/gpt-5.5'))[0];
        $this->assertFalse($gpt['featured']);
        $this->assertNull($gpt['intelligence_index']);
    }
}
