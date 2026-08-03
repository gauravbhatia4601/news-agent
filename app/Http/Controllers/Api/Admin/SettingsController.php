<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private const SETTING_KEYS = [
        'generation.provider',
        'generation.model',
        'generation.timeout',
        'generation.enabled',
        'generation.fallback_provider',
        'generation.fallback_model',
    ];

    public function index(): JsonResponse
    {
        $providers = $this->buildProviderList();

        $settings = [
            'provider' => Setting::get('generation.provider', (string) config('news-engine.generation.provider', 'openrouter')),
            'model' => Setting::get('generation.model', (string) config('news-engine.generation.model', '')),
            'timeout' => (int) Setting::get('generation.timeout', (string) config('news-engine.generation.timeout', 300)),
            'enabled' => Setting::get('generation.enabled') !== null
                ? (bool) Setting::get('generation.enabled')
                : (bool) config('news-engine.generation.enabled', true),
            'fallback_provider' => Setting::get('generation.fallback_provider') ?? (string) config('news-engine.generation.fallback_provider', ''),
            'fallback_model' => Setting::get('generation.fallback_model') ?? (string) config('news-engine.generation.fallback_model', ''),
        ];

        $defaults = [
            'provider' => (string) config('news-engine.generation.provider', 'openrouter'),
            'model' => (string) config('news-engine.generation.model', ''),
            'timeout' => (int) config('news-engine.generation.timeout', 300),
            'enabled' => (bool) config('news-engine.generation.enabled', true),
            'fallback_provider' => (string) config('news-engine.generation.fallback_provider', ''),
            'fallback_model' => (string) config('news-engine.generation.fallback_model', ''),
        ];

        return response()->json([
            'data' => [
                'settings' => $settings,
                'defaults' => $defaults,
                'providers' => $providers,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|max:100',
            'model' => 'required|string|max:200',
            'timeout' => 'nullable|integer|min:30|max:1800',
            'enabled' => 'nullable|boolean',
            'fallback_provider' => 'nullable|string|max:100',
            'fallback_model' => 'nullable|string|max:200',
        ]);

        Setting::set('generation.provider', $validated['provider']);
        Setting::set('generation.model', $validated['model']);
        Setting::set('generation.timeout', (string) ($validated['timeout'] ?? 300));
        Setting::set('generation.enabled', isset($validated['enabled']) ? ($validated['enabled'] ? '1' : '0') : '1');
        Setting::set('generation.fallback_provider', $validated['fallback_provider'] ?? '');
        Setting::set('generation.fallback_model', $validated['fallback_model'] ?? '');

        AuditLogService::log('update', 'Settings', null, $validated);

        return response()->json([
            'data' => [
                'provider' => $validated['provider'],
                'model' => $validated['model'],
                'timeout' => (int) ($validated['timeout'] ?? 300),
                'enabled' => isset($validated['enabled']) ? (bool) $validated['enabled'] : true,
                'fallback_provider' => $validated['fallback_provider'] ?? '',
                'fallback_model' => $validated['fallback_model'] ?? '',
            ],
        ]);
    }

    private function buildProviderList(): array
    {
        $configuredProviders = config('ai.providers', []);
        $providerLabels = [
            'anthropic' => 'Anthropic (Claude)',
            'azure' => 'Azure OpenAI',
            'cohere' => 'Cohere',
            'deepseek' => 'DeepSeek',
            'gemini' => 'Google Gemini',
            'groq' => 'Groq',
            'jina' => 'Jina AI',
            'mistral' => 'Mistral AI',
            'ollama' => 'Ollama (Local)',
            'openai' => 'OpenAI',
            'ollama_cloud' => 'Ollama Cloud',
            'openrouter' => 'OpenRouter',
            'xai' => 'xAI (Grok)',
        ];

        $providers = [];
        foreach ($configuredProviders as $name => $config) {
            if (in_array($name, ['eleven', 'voyageai'])) {
                continue;
            }

            $key = $config['key'] ?? null;
            $providers[] = [
                'value' => $name,
                'label' => $providerLabels[$name] ?? ucfirst($name),
                'has_key' => ! empty($key),
                'is_ollama' => str_starts_with($name, 'ollama'),
            ];
        }

        usort($providers, function ($a, $b) {
            $aPriority = $a['has_key'] ? 0 : 1;
            $bPriority = $b['has_key'] ? 0 : 1;
            if ($aPriority !== $bPriority) {
                return $aPriority - $bPriority;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $providers;
    }
}
