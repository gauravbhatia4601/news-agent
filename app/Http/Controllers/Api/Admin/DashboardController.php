<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $articlesTotal = NewsArticle::count();
        $articlesPublished = NewsArticle::where('status', 'published')->count();
        $articlesDraft = NewsArticle::where('status', 'draft')->count();

        $topicsTotal = NewsTopic::count();
        $topicsGenerated = NewsTopic::where('generation_status', 'generated')->count();
        $topicsPending = NewsTopic::where('generation_status', 'pending')->count();
        $topicsFailed = NewsTopic::where('generation_status', 'failed')->count();
        $topicsDuplicateSkipped = NewsTopic::where('generation_status', 'duplicate_skipped')->count();

        $queueJobs = Queue::size(config('queue.connections.'.config('queue.default', 'database').'.queue', 'default'));
        $failedQueueJobs = DB::table('failed_jobs')->count();

        $recentArticles = NewsArticle::with('topic.categoryRelation')
            ->where('status', 'published')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'slug' => $a->slug,
                'title' => $a->title,
                'status' => $a->status,
                'category' => $a->topic?->categoryRelation?->name,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        $recentFailedTopics = NewsTopic::where('generation_status', 'failed')
            ->orderByDesc('updated_at')
            ->take(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'topic_name' => $t->topic_name,
                'category' => $t->category,
                'retry_count' => $t->retry_count,
                'updated_at' => $t->updated_at->toIso8601String(),
            ]);

        $parentCategories = Category::with('children')->whereNull('parent_id')->get();

        $categoryCounts = $parentCategories->map(function ($parent) {
            $childIds = $parent->children->pluck('id')->push($parent->id)->all();

            $count = NewsTopic::whereIn('category_id', $childIds)
                ->whereHas('article', fn ($q) => $q->where('status', 'published'))
                ->count();

            return [
                'name' => $parent->name,
                'slug' => $parent->slug,
                'article_count' => $count,
            ];
        })->sortByDesc('article_count')->values();

        $successRate = $topicsTotal > 0
            ? round(($topicsGenerated / $topicsTotal) * 100, 1)
            : 0;

        $dailyTrend = $this->dailyTrend();

        return response()->json([
            'data' => [
                'articles' => [
                    'total' => $articlesTotal,
                    'published' => $articlesPublished,
                    'draft' => $articlesDraft,
                ],
                'topics' => [
                    'total' => $topicsTotal,
                    'generated' => $topicsGenerated,
                    'pending' => $topicsPending,
                    'failed' => $topicsFailed,
                    'duplicate_skipped' => $topicsDuplicateSkipped,
                ],
                'queue_jobs' => $queueJobs,
                'failed_queue_jobs' => $failedQueueJobs,
                'success_rate' => $successRate,
                'recent_articles' => $recentArticles,
                'recent_failed_topics' => $recentFailedTopics,
                'category_article_counts' => $categoryCounts,
                'daily_trend' => $dailyTrend,
            ],
        ]);
    }

    private function dailyTrend(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->startOfDay())->values();
        $labels = $days->map(fn ($d) => $d->format('D'))->all();
        $dates = $days->map(fn ($d) => $d->toDateString())->all();

        $articlesByDay = NewsArticle::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $days->first())
            ->groupBy('date')
            ->pluck('count', 'date')
            ->all();

        $topicsByDay = NewsTopic::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $days->first())
            ->groupBy('date')
            ->pluck('count', 'date')
            ->all();

        return [
            'labels' => $labels,
            'dates' => $dates,
            'articles' => array_map(fn ($date) => (int) ($articlesByDay[$date] ?? 0), $dates),
            'topics' => array_map(fn ($date) => (int) ($topicsByDay[$date] ?? 0), $dates),
        ];
    }
}
