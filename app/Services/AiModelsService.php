<?php

namespace App\Services;

use App\Models\AiModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Serves the AI model catalog from the database only — never live from the
 * feed (see docs/adr/0001-ai-model-data-from-db-not-live-feed.md).
 */
class AiModelsService
{
    public function get(): array
    {
        $models = AiModel::query()
            ->where('is_active', true)
            ->where('variant', 'base')
            ->with('provider:id,slug,name')
            ->orderBy('name')
            ->get();

        $featured = array_flip((array) config('news-engine.ai_models.featured', []));

        // ISO 8601 — the raw DB string ("2026-09-28 10:08:21") isn't parseable
        // by the frontend's date helpers.
        $syncedAt = DB::table('ai_sync_state')->where('source', 'openrouter')->value('synced_at');

        return [
            'synced_at' => $syncedAt ? Carbon::parse($syncedAt)->toIso8601String() : null,
            'models' => $models->map(fn (AiModel $model) => [
                'feed_id' => $model->feed_id,
                'name' => $model->name,
                'provider' => $model->provider?->slug,
                'provider_name' => $model->provider?->name,
                'context_length' => $model->context_length,
                'input_price_per_million' => $model->input_price_per_million !== null ? (float) $model->input_price_per_million : null,
                'output_price_per_million' => $model->output_price_per_million !== null ? (float) $model->output_price_per_million : null,
                'modality' => $model->modality,
                'featured' => isset($featured[$model->feed_id]),
            ])->all(),
        ];
    }
}
