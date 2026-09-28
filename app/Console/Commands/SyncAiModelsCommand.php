<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\AiModelProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncAiModelsCommand extends Command
{
    protected $signature = 'ai:sync-models';

    protected $description = 'Sync the AI model catalog from the OpenRouter feed into the database';

    private const FEED_URL = 'https://openrouter.ai/api/v1/models';

    private const SOURCE = 'openrouter';

    public function handle(): int
    {
        try {
            $response = Http::timeout(30)->get(self::FEED_URL);
        } catch (\Throwable $e) {
            Log::warning('AI model sync failed: '.$e->getMessage());
            $this->error('Feed request failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->ok()) {
            Log::warning('AI model sync failed: HTTP '.$response->status());
            $this->error('Feed returned HTTP '.$response->status());

            return self::FAILURE;
        }

        $rows = $response->json('data') ?? [];
        if ($rows === []) {
            Log::warning('AI model sync: empty feed');
            $this->error('Feed returned no models');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $seenFeedIds = [];

        DB::transaction(function () use ($rows, &$created, &$updated, &$seenFeedIds) {
            foreach ($rows as $row) {
                $feedId = (string) ($row['id'] ?? '');
                if ($feedId === '' || ! str_contains($feedId, '/')) {
                    continue;
                }

                [$providerSlug] = explode('/', $feedId, 2);
                $variant = $this->variantOf($feedId);

                // Skip rows without a usable input price before touching the provider
                // table — a bad row shouldn't leave a provider behind. Free variants
                // (zero-priced by definition) are stored with null prices instead.
                $inputPrice = $this->pricePerMillion($row['pricing']['prompt'] ?? null);
                if ($inputPrice === null && $variant !== 'free') {
                    continue;
                }

                $provider = AiModelProvider::firstOrCreate(
                    ['slug' => $providerSlug],
                    ['name' => $providerSlug]
                );

                $attrs = [
                    'ai_model_provider_id' => $provider->id,
                    'feed_id' => $feedId,
                    'name' => (string) ($row['name'] ?? $feedId),
                    'context_length' => is_numeric($row['context_length'] ?? null) ? (int) $row['context_length'] : null,
                    'input_price_per_million' => $inputPrice,
                    'output_price_per_million' => $this->pricePerMillion($row['pricing']['completion'] ?? null),
                    'modality' => $row['architecture']['modality'] ?? null,
                    'variant' => $variant,
                    'is_active' => true,
                    'last_seen_at' => now(),
                ];

                $existing = AiModel::where('feed_id', $feedId)->first();
                if ($existing === null) {
                    AiModel::create($attrs + ['source' => self::SOURCE]);
                    $created++;
                } else {
                    $existing->fill($attrs)->save();
                    $updated++;
                }

                $seenFeedIds[] = $feedId;
            }

            // Deactivate only models synced from this source that vanished from the feed.
            AiModel::where('source', self::SOURCE)
                ->whereNotIn('feed_id', $seenFeedIds)
                ->update(['is_active' => false, 'last_seen_at' => DB::raw('last_seen_at')]);
        });

        DB::table('ai_sync_state')->updateOrInsert(
            ['source' => self::SOURCE],
            ['synced_at' => now()]
        );

        $this->info("Synced: {$created} created, {$updated} updated.");

        return self::SUCCESS;
    }

    /**
     * Convert a per-token USD string ("0.000002") to USD per million tokens (2.0).
     * Non-numeric or zero-prompt values → null (row skipped upstream).
     */
    private function pricePerMillion(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $perToken = (float) $value;

        return $perToken > 0 ? $perToken * 1_000_000 : null;
    }

    private function variantOf(string $feedId): string
    {
        if (str_ends_with($feedId, ':batch')) {
            return 'batch';
        }

        if (str_ends_with($feedId, ':free')) {
            return 'free';
        }

        return 'base';
    }
}
