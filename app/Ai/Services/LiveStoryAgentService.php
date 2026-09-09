<?php

namespace App\Ai\Services;

use App\Ai\Agents\LiveStoryDetectionAgent;
use App\Ai\Agents\LiveUpdateJudgeAgent;
use App\Ai\Agents\PlainTextLiveStoryDetectionAgent;
use App\Ai\Agents\PlainTextLiveUpdateJudgeAgent;
use App\Models\AiInvocation;
use App\Models\Setting;
use App\Models\Story;
use Illuminate\Support\Facades\Log;

class LiveStoryAgentService
{
    /**
     * Triage freshly-discovered topics — decide which are ongoing live events.
     *
     * @param  array<int, array{topic_signature: string, topic_name: string, category: string, dominant_term: string, headlines: string[]}>  $topicBatch
     * @return array<int, array{topic_signature: string, is_live_event: bool, suggested_title: string, search_query: string, urgency: string, reasoning: string}>
     */
    public function detectLiveStories(array $topicBatch): array
    {
        if ($topicBatch === []) {
            return [];
        }

        [$provider, $model, $timeout] = $this->resolveProvider();

        $isOllama = str_starts_with($provider, 'ollama');
        $agent = $isOllama ? new PlainTextLiveStoryDetectionAgent : new LiveStoryDetectionAgent;

        $payload = json_encode([
            'instruction' => 'Triage each topic below. For each, decide if it is an ongoing LIVE EVENT (continuing narrative, expected future developments) or a one-off story.',
            'topics' => $topicBatch,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $start = microtime(true);

        try {
            $response = $agent->prompt($payload, [], $provider, $model, $timeout);
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            $result = $isOllama ? $this->parseJsonResponse($response) : $response;

            $this->recordInvocation($provider, $model, $response->usage ?? null, $response->invocationId ?? null, $durationMs);

            $detected = $result['detected_stories'] ?? [];

            return is_array($detected) ? $detected : [];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $this->recordInvocation($provider, $model, null, null, $durationMs, 'failed', $e->getMessage());
            Log::warning('LiveStoryDetectionAgent failed.', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Batched judge: decide which candidate topics are genuine new developments.
     *
     * @param  array<int, array{topic_signature: string, headline: string, summary: string}>  $candidates
     * @param  array{headline: string, summary: string}|null  $lastUpdate
     * @return array<string, array{topic_signature: string, is_new_development: bool, urgency_adjustment: string, reasoning: string}>
     */
    public function judgeUpdateBatch(Story $story, array $candidates, ?array $lastUpdate): array
    {
        if ($candidates === []) {
            return [];
        }

        if (! (bool) config('news-engine.live_stories.judge_enabled', true)) {
            return [];
        }

        [$provider, $model, $timeout] = $this->resolveProvider();

        $isOllama = str_starts_with($provider, 'ollama');
        $agent = $isOllama ? new PlainTextLiveUpdateJudgeAgent : new LiveUpdateJudgeAgent;

        $payload = json_encode([
            'story_title' => $story->title,
            'last_update' => $lastUpdate,
            'candidates' => $candidates,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $start = microtime(true);

        try {
            $response = $agent->prompt($payload, [], $provider, $model, $timeout);
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            $result = $isOllama ? $this->parseJsonResponse($response) : $response;

            $this->recordInvocation($provider, $model, $response->usage ?? null, $response->invocationId ?? null, $durationMs);

            $verdicts = $result['verdicts'] ?? [];
            if (! is_array($verdicts)) {
                return [];
            }

            // Key by topic_signature for O(1) lookup.
            $keyed = [];
            foreach ($verdicts as $verdict) {
                if (isset($verdict['topic_signature'])) {
                    $keyed[$verdict['topic_signature']] = $verdict;
                }
            }

            return $keyed;
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $this->recordInvocation($provider, $model, null, null, $durationMs, 'failed', $e->getMessage());
            Log::warning('LiveUpdateJudgeAgent failed.', ['story_id' => $story->id, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Resolve provider/model/timeout from live_stories config, falling back to generation config.
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function resolveProvider(): array
    {
        $provider = Setting::get('live_stories.agent_provider')
            ?? config('news-engine.live_stories.agent_provider')
            ?? Setting::get('generation.provider')
            ?? (string) config('news-engine.generation.provider');

        $model = Setting::get('live_stories.agent_model')
            ?? config('news-engine.live_stories.agent_model')
            ?? Setting::get('generation.model')
            ?? (string) config('news-engine.generation.model');

        $timeout = (int) (Setting::get('generation.timeout') ?? (string) config('news-engine.generation.timeout', 300));

        return [(string) $provider, (string) $model, $timeout];
    }

    private function recordInvocation(string $provider, string $model, $usage, ?string $invocationId, int $durationMs, string $status = 'success', ?string $error = null): void
    {
        try {
            AiInvocation::create([
                'topic_id' => null,
                'provider' => $provider,
                'model' => $model,
                'invocation_id' => $invocationId,
                'prompt_tokens' => $usage?->promptTokens ?? 0,
                'completion_tokens' => $usage?->completionTokens ?? 0,
                'cache_write_tokens' => $usage?->cacheWriteInputTokens ?? 0,
                'cache_read_tokens' => $usage?->cacheReadInputTokens ?? 0,
                'reasoning_tokens' => $usage?->reasoningTokens ?? 0,
                'total_tokens' => ($usage?->promptTokens ?? 0) + ($usage?->completionTokens ?? 0),
                'duration_ms' => $durationMs,
                'status' => $status,
                'error' => $error ? mb_substr($error, 0, 500) : null,
                'invoked_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record AI invocation.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Extract a JSON object from a plain-text model response.
     * Mirrors the parseJsonResponse pattern in NewsArticleGenerationService.
     */
    private function parseJsonResponse(mixed $raw): array
    {
        $text = is_string($raw) ? $raw : (string) $raw;

        $text = preg_replace('/^```json\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = preg_replace('/^```\s*/', '', $text) ?? $text;
        $text = trim($text);

        $start = strpos($text, '{');
        if ($start === false) {
            throw new \RuntimeException('No JSON object found in model response. Raw: '.substr($text, 0, 500));
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $end = null;

        for ($i = $start; $i < strlen($text); $i++) {
            $ch = $text[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;

                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;

                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($ch === '"') {
                $inString = true;

                continue;
            }

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        if ($end === null) {
            throw new \RuntimeException('Unterminated JSON object in model response. Raw: '.substr($text, 0, 500));
        }

        $json = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            // ponytail: same minimal trailing-comma repair as NewsArticleGenerationService
            $fixed = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;
            $decoded = json_decode($fixed, true);
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('Failed to decode JSON from model response. Raw: '.substr($json, 0, 500));
        }

        return $decoded;
    }
}
