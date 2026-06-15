<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateArticle;
use App\Models\Category;
use App\News\Services\NewsDiscoveryService;
use App\News\Repositories\NewsTopicRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    public function __construct(
        private readonly NewsDiscoveryService $discoveryService,
        private readonly NewsTopicRepository $topicRepository,
    ) {}

    public function trigger(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 3);
        $freshHours = $request->integer('fresh_hours', 24);
        $sourcesPerTopic = $request->integer('sources_per_topic', 3);
        $queue = $request->boolean('queue', true);

        $limit = max(1, min($limit, 20));
        $freshHours = max(1, min($freshHours, 48));
        $sourcesPerTopic = max(2, min($sourcesPerTopic, 6));

        $categories = Category::whereNotNull('parent_id')
            ->orderBy('display_order')
            ->get(['id', 'slug', 'name'])
            ->map(fn ($cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'category_id' => $cat->id,
            ])
            ->all();

        try {
            $topics = $this->discoveryService->discover($categories, $limit, $freshHours, $sourcesPerTopic);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Discovery failed: ' . $e->getMessage()], 500);
        }

        $signatures = [];
        foreach ($topics as $topic) {
            $signatures[] = $topic->signature;
        }

        if ($queue && $signatures !== []) {
            foreach ($signatures as $sig) {
                GenerateArticle::dispatch($sig);
            }
        }

        return response()->json([
            'data' => [
                'discovered' => count($topics),
                'topics' => array_map(fn ($t) => [
                    'name' => $t->name,
                    'category' => $t->category,
                    'source_count' => $t->sourceCount(),
                    'signature' => $t->signature,
                ], $topics),
                'queued_for_generation' => $queue ? count($signatures) : 0,
            ],
        ]);
    }

    public function retryFailed(Request $request): JsonResponse
    {
        $maxRetries = $request->integer('max_retries', 3);
        $signatures = $this->topicRepository->retryFailed($maxRetries);

        foreach ($signatures as $sig) {
            GenerateArticle::dispatch($sig);
        }

        return response()->json([
            'data' => [
                'retried' => count($signatures),
                'signatures' => $signatures,
            ],
        ]);
    }
}