<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateArticle;
use App\Models\Category;
use App\Models\NewsTopic;
use App\News\Repositories\NewsTopicRepository;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TopicController extends Controller
{
    public function __construct(
        private readonly NewsTopicRepository $topicRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = NewsTopic::with('categoryRelation', 'locationCategory', 'sources', 'article');

        if ($request->filled('status')) {
            $query->where('generation_status', $request->status);
        }

        if ($request->filled('category')) {
            $slug = $request->category;

            $india = Category::where('slug', 'india')->whereNull('parent_id')->first();
            $isState = $india && Category::where('slug', $slug)->where('parent_id', $india->id)->exists();

            if ($isState || $slug === 'india') {
                if ($slug === 'india') {
                    $stateIds = Category::where('parent_id', $india->id)->pluck('id');
                    $query->whereIn('location_category_id', $stateIds);
                } else {
                    $state = Category::where('slug', $slug)->where('parent_id', $india->id)->first();
                    if ($state) {
                        $query->where('location_category_id', $state->id);
                    }
                }
            } else {
                $query->whereHas('categoryRelation', fn ($q) => $q->where('slug', $slug));
            }
        }

        if ($request->filled('search')) {
            $query->where('topic_name', 'ilike', '%'.$request->search.'%');
        }

        $perPage = $request->integer('per_page', 20);
        $topics = $query->orderByDesc('updated_at')->paginate(min($perPage, 100));

        return response()->json($topics->through(fn ($t) => [
            'id' => $t->id,
            'topic_name' => $t->topic_name,
            'category' => $t->categoryRelation?->name ?? $t->category,
            'category_slug' => $t->categoryRelation?->slug,
            'location' => $t->locationCategory ? [
                'id' => $t->locationCategory->id,
                'name' => $t->locationCategory->name,
                'slug' => $t->locationCategory->slug,
            ] : null,
            'generation_status' => $t->generation_status,
            'retry_count' => $t->retry_count,
            'source_count' => $t->source_count,
            'has_article' => $t->article !== null,
            'article_id' => $t->article?->id,
            'article_status' => $t->article?->status,
            'created_at' => $t->created_at->toIso8601String(),
            'updated_at' => $t->updated_at->toIso8601String(),
        ]));
    }

    public function show(int $id): JsonResponse
    {
        $topic = NewsTopic::with('categoryRelation', 'sources', 'article')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $topic->id,
                'topic_name' => $topic->topic_name,
                'category' => $topic->categoryRelation?->name ?? $topic->category,
                'category_slug' => $topic->categoryRelation?->slug,
                'topic_signature' => $topic->topic_signature,
                'generation_status' => $topic->generation_status,
                'retry_count' => $topic->retry_count,
                'source_count' => $topic->source_count,
                'sources' => $topic->sources->map(fn ($s) => [
                    'id' => $s->id,
                    'source_name' => $s->source_name,
                    'source_url' => $s->source_url,
                    'headline' => $s->headline,
                    'summary' => Str::limit($s->summary ?? '', 300),
                    'published_at' => $s->published_at?->toIso8601String(),
                ]),
                'article' => $topic->article ? [
                    'id' => $topic->article->id,
                    'slug' => $topic->article->slug,
                    'title' => $topic->article->title,
                    'status' => $topic->article->status,
                ] : null,
                'created_at' => $topic->created_at->toIso8601String(),
                'updated_at' => $topic->updated_at->toIso8601String(),
            ],
        ]);
    }

    public function retry(int $id): JsonResponse
    {
        $topic = NewsTopic::findOrFail($id);

        if ($topic->generation_status !== 'failed') {
            return response()->json(['message' => 'Can only retry failed topics'], 422);
        }

        if ($topic->retry_count >= 5) {
            return response()->json(['message' => 'Maximum retry limit (5) reached for this topic'], 422);
        }

        $topic->update(['generation_status' => 'pending']);

        GenerateArticle::dispatch($topic->topic_signature);

        AuditLogService::log('retry', 'Topic', $id);

        return response()->json(['message' => 'Retry dispatched', 'topic_signature' => $topic->topic_signature]);
    }

    public function dispatch(int $id): JsonResponse
    {
        $topic = NewsTopic::findOrFail($id);

        if ($topic->generation_status !== 'pending') {
            return response()->json(['message' => 'Can only dispatch pending topics'], 422);
        }

        GenerateArticle::dispatch($topic->topic_signature);

        AuditLogService::log('dispatch', 'Topic', $id);

        return response()->json(['message' => 'Dispatched to queue', 'topic_signature' => $topic->topic_signature]);
    }

    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:news_topics,id',
            'action' => 'required|in:delete,dispatch,retry',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];

        if ($action === 'delete') {
            $topics = NewsTopic::whereIn('id', $ids)->get();
            foreach ($topics as $topic) {
                if ($topic->article) {
                    $topic->article->delete();
                }
                $topic->sources()->delete();
                $topic->delete();
            }
            AuditLogService::log('batch_delete', 'Topic', null, ['ids' => $ids]);

            return response()->json(['message' => count($ids).' topics deleted']);
        }

        if ($action === 'dispatch') {
            $count = 0;
            $topics = NewsTopic::whereIn('id', $ids)->where('generation_status', 'pending')->get();
            foreach ($topics as $topic) {
                GenerateArticle::dispatch($topic->topic_signature);
                $count++;
            }
            AuditLogService::log('batch_dispatch', 'Topic', null, ['ids' => $ids]);

            return response()->json(['message' => $count.' topics dispatched to queue']);
        }

        if ($action === 'retry') {
            $count = 0;
            $topics = NewsTopic::whereIn('id', $ids)->where('generation_status', 'failed')->where('retry_count', '<', 5)->get();
            foreach ($topics as $topic) {
                $topic->update(['generation_status' => 'pending']);
                GenerateArticle::dispatch($topic->topic_signature);
                $count++;
            }
            AuditLogService::log('batch_retry', 'Topic', null, ['ids' => $ids]);

            return response()->json(['message' => $count.' failed topics retried']);
        }

        return response()->json(['message' => 'Unknown action'], 422);
    }

    public function destroy(int $id): JsonResponse
    {
        $topic = NewsTopic::findOrFail($id);

        if ($topic->article) {
            $topic->article->delete();
        }

        $topic->sources()->delete();
        $topic->delete();

        AuditLogService::log('delete', 'Topic', $id);

        return response()->json(['message' => 'Topic and associated data deleted']);
    }
}
