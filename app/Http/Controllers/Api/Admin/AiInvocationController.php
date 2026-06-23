<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInvocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiInvocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:success,failed,all',
            'provider' => 'nullable|string|max:60',
            'per_page' => 'nullable|integer|min:10|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        $status = $validated['status'] ?? 'all';
        $perPage = (int) ($validated['per_page'] ?? 25);
        $provider = $validated['provider'] ?? null;

        $query = AiInvocation::query()->with('topic:id,topic_name')->orderByDesc('invoked_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($provider) {
            $query->where('provider', $provider);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', (int) ($validated['page'] ?? 1));

        $stats = [
            'total_invocations' => AiInvocation::count(),
            'success_count' => AiInvocation::where('status', 'success')->count(),
            'failed_count' => AiInvocation::where('status', 'failed')->count(),
            'total_prompt_tokens' => AiInvocation::sum('prompt_tokens'),
            'total_completion_tokens' => AiInvocation::sum('completion_tokens'),
            'total_tokens' => AiInvocation::sum('total_tokens'),
            'avg_duration_ms' => (int) AiInvocation::avg('duration_ms'),
            'by_provider' => AiInvocation::selectRaw('provider, model, COUNT(*) as count, SUM(total_tokens) as tokens')
                ->groupBy('provider', 'model')
                ->get(),
        ];

        return response()->json([
            'data' => [
                'invocations' => $paginated->items(),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'stats' => $stats,
            ],
        ]);
    }
}