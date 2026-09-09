<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\Story;
use App\Models\StoryUpdate;
use App\News\Services\MonitorLiveStoriesService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminStoryController extends Controller
{
    public function __construct(
        private readonly MonitorLiveStoriesService $monitorService,
    ) {}

    /**
     * Paginated list with urgency/status/search filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Story::query()->with('category')->withCount(['articles', 'updates']);

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->string('urgency')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            // LOWER() comparison — portable across PostgreSQL (case-insensitive) and SQLite (tests).
            $query->whereRaw('LOWER(title) LIKE LOWER(?)', ['%'.$search.'%']);
        }

        $perPage = min($request->integer('per_page', 20), 100);
        $stories = $query->orderByDesc('started_at')->paginate($perPage);

        return response()->json($stories->through(fn ($s) => [
            'id' => $s->id,
            'slug' => $s->slug,
            'title' => $s->title,
            'description' => $s->description,
            'search_query' => $s->search_query,
            'category' => $s->category?->only(['id', 'name', 'slug']),
            'urgency' => $s->urgency,
            'status' => $s->status,
            'started_at' => $s->started_at?->toIso8601String(),
            'concluded_at' => $s->concluded_at?->toIso8601String(),
            'last_monitored_at' => $s->last_monitored_at?->toIso8601String(),
            'update_count' => $s->updates_count,
            'created_at' => $s->created_at->toIso8601String(),
            'updated_at' => $s->updated_at->toIso8601String(),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'search_query' => 'required|string|max:255',
            'urgency' => 'required|in:live,developing,ongoing',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string|max:2000',
        ]);

        $slug = $this->generateUniqueStorySlug($validated['title']);

        $story = Story::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'search_query' => $validated['search_query'],
            'urgency' => $validated['urgency'],
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => 'admin-created',
            'started_at' => now(),
            'monitor_interval_minutes' => 10,
        ]);

        AuditLogService::log('create', 'Story', $story->id, $validated);

        return response()->json([
            'data' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'urgency' => $story->urgency,
                'status' => $story->status,
            ],
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $story = Story::with('category')->findOrFail($id);

        // Timeline = discrete timestamped updates (latest 10).
        $timeline = StoryUpdate::where('story_id', $story->id)
            ->orderByDesc('event_at')
            ->limit(10)
            ->get(['id', 'content', 'event_at', 'source_name', 'source_url']);

        return response()->json([
            'data' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'description' => $story->description,
                'search_query' => $story->search_query,
                'category' => $story->category?->only(['id', 'name', 'slug']),
                'urgency' => $story->urgency,
                'status' => $story->status,
                'started_at' => $story->started_at?->toIso8601String(),
                'concluded_at' => $story->concluded_at?->toIso8601String(),
                'last_monitored_at' => $story->last_monitored_at?->toIso8601String(),
                'monitor_interval_minutes' => $story->monitor_interval_minutes,
                'timeline' => $timeline->map(fn ($u) => [
                    'id' => $u->id,
                    'content' => $u->content,
                    'event_at' => $u->event_at?->toIso8601String(),
                    'source_name' => $u->source_name,
                    'source_url' => $u->source_url,
                ]),
                'created_at' => $story->created_at->toIso8601String(),
                'updated_at' => $story->updated_at->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $story = Story::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'search_query' => 'sometimes|required|string|max:255',
            'urgency' => 'sometimes|in:live,developing,ongoing,concluded',
            'description' => 'nullable|string|max:2000',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $story->update($validated);

        AuditLogService::log('update', 'Story', $story->id, $validated);

        return response()->json([
            'data' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'urgency' => $story->urgency,
                'status' => $story->status,
            ],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $story = Story::findOrFail($id);

        DB::transaction(function () use ($story) {
            // Detach linked articles before deleting so they survive the story.
            NewsArticle::where('story_id', $story->id)->update(['story_id' => null]);
            $story->topics()->detach();
            $story->delete();
        });

        AuditLogService::log('delete', 'Story', $id);

        return response()->json(['message' => 'Story deleted']);
    }

    /**
     * Reactivate a concluded story.
     */
    public function activate(int $id): JsonResponse
    {
        $story = Story::findOrFail($id);

        $story->update([
            'urgency' => $story->urgency === 'concluded' ? 'developing' : $story->urgency,
            'concluded_at' => null,
        ]);

        AuditLogService::log('activate', 'Story', $story->id);

        return response()->json([
            'data' => [
                'id' => $story->id,
                'urgency' => $story->urgency,
                'concluded_at' => null,
            ],
        ]);
    }

    /**
     * Mark a story as concluded.
     */
    public function conclude(int $id): JsonResponse
    {
        $story = Story::findOrFail($id);

        $story->update([
            'urgency' => 'concluded',
            'concluded_at' => now(),
        ]);

        AuditLogService::log('conclude', 'Story', $story->id);

        return response()->json([
            'data' => [
                'id' => $story->id,
                'urgency' => $story->urgency,
                'concluded_at' => $story->concluded_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Run one monitor cycle synchronously and return the counts.
     */
    public function triggerMonitor(int $id): JsonResponse
    {
        $story = Story::findOrFail($id);

        try {
            $result = $this->monitorService->runCycle($story);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Monitor cycle failed: '.$e->getMessage(),
            ], 500);
        }

        AuditLogService::log('trigger_monitor', 'Story', $story->id, $result);

        return response()->json([
            'data' => [
                'story_id' => $story->id,
                'discovered' => $result['discovered'] ?? 0,
                'judged_new' => $result['judged_new'] ?? 0,
                'dispatched' => $result['dispatched'] ?? 0,
                'concluded' => $result['concluded'] ?? false,
            ],
        ]);
    }

    /**
     * Batch conclude or delete stories by ids.
     */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:stories,id',
            'action' => 'required|in:conclude,delete',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];

        if ($action === 'conclude') {
            Story::whereIn('id', $ids)->update([
                'urgency' => 'concluded',
                'concluded_at' => now(),
            ]);

            AuditLogService::log('batch_conclude', 'Story', null, ['ids' => $ids]);

            return response()->json(['message' => count($ids).' stories concluded']);
        }

        DB::transaction(function () use ($ids) {
            NewsArticle::whereIn('story_id', $ids)->update(['story_id' => null]);
            Story::whereIn('id', $ids)->get()->each(function ($story) {
                $story->topics()->detach();
                $story->delete();
            });
        });

        AuditLogService::log('batch_delete', 'Story', null, ['ids' => $ids]);

        return response()->json(['message' => count($ids).' stories deleted']);
    }

    /**
     * Generate a unique slug for a story, mirroring NewsTopicRepository's pattern.
     */
    private function generateUniqueStorySlug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'story';
        }

        $slug = $base;
        $counter = 2;

        while (DB::table('stories')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
