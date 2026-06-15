<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Services\SitemapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GenerationController extends Controller
{
    public function queueStatus(): JsonResponse
    {
        $pending = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $recentFailed = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->take(10)
            ->get()
            ->map(fn ($j) => [
                'id' => $j->id,
                'queue' => $j->queue,
                'failed_at' => $j->failed_at,
                'exception' => Str::limit($j->exception, 300),
            ]);

        return response()->json([
            'data' => [
                'pending_jobs' => $pending,
                'total_failed_jobs' => $failedJobs,
                'recent_failed' => $recentFailed,
            ],
        ]);
    }

    public function regenerateSitemap(): JsonResponse
    {
        $service = new SitemapService();
        $files = $service->generate();

        return response()->json([
            'data' => [
                'message' => 'Sitemaps regenerated',
                'files' => $files,
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $now = now();
        $last24h = $now->clone()->subHours(24);
        $last7d = $now->clone()->subDays(7);
        $last30d = $now->clone()->subDays(30);

        $topicsGenerated = DB::table('news_topics')->where('generation_status', 'generated')->count();
        $topicsFailed = DB::table('news_topics')->where('generation_status', 'failed')->count();
        $topicsPending = DB::table('news_topics')->where('generation_status', 'pending')->count();

        $articlesPublished = DB::table('news_articles')->where('status', 'published')->count();
        $articlesDraft = DB::table('news_articles')->where('status', 'draft')->count();

        // Time-based article counts
        $articles24h = NewsArticle::where('created_at', '>=', $last24h)->count();
        $articles7d = NewsArticle::where('created_at', '>=', $last7d)->count();
        $articles30d = NewsArticle::where('created_at', '>=', $last30d)->count();

        // Average word count
        $avgWordCount = NewsArticle::selectRaw('AVG(LENGTH(content) - LENGTH(REPLACE(content, \' \', \'\')) + 1) as avg_words')
            ->value('avg_words') ?? 0;

        // Quality gate stats
        $qualityReports = DB::table('news_articles')
            ->whereNotNull('quality_report')
            ->pluck('quality_report')
            ->map(fn ($q) => json_decode($q, true));
        $qualityPassed = $qualityReports->filter(fn ($q) => empty($q['issues'] ?? []))->count();
        $qualityFailed = $qualityReports->count() - $qualityPassed;

        // Model usage
        $modelUsage = NewsArticle::selectRaw('model, COUNT(*) as count')
            ->groupBy('model')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['model' => $r->model, 'count' => $r->count]);

        $parentCategories = Category::with('children')->whereNull('parent_id')->get();

        $topCategories = $parentCategories->map(function ($parent) {
            $childIds = $parent->children->pluck('id')->push($parent->id)->all();

            $count = NewsTopic::whereIn('category_id', $childIds)
                ->whereHas('article', fn ($q) => $q->where('status', 'published'))
                ->count();

            return [
                'name' => $parent->name,
                'slug' => $parent->slug,
                'count' => $count,
            ];
        })->sortByDesc('count')->values();

        // Discovery stats
        $discovered24h = NewsTopic::where('created_at', '>=', $last24h)->count();
        $discovered7d = NewsTopic::where('created_at', '>=', $last7d)->count();

        // Image stats
        $articlesWithImages = NewsArticle::whereNotNull('image_url')->count();
        $aiImages = NewsArticle::where('metadata', 'like', '%"image_origin":"ai"%')->count();
        $sourceImages = NewsArticle::where('metadata', 'like', '%"image_origin":"source"%')->count();

        $avgGenDuration = NewsArticle::where('created_at', '>=', $last24h)
            ->where('generation_duration_seconds', '>', 0)
            ->selectRaw('AVG(generation_duration_seconds) as avg_seconds')
            ->value('avg_seconds') ?? 0;

        $throughput24h = $articles24h > 0 ? round($articles24h / 24, 1) : 0;

        return response()->json([
            'data' => [
                'topics' => [
                    'total' => $topicsGenerated + $topicsFailed + $topicsPending,
                    'generated' => $topicsGenerated,
                    'failed' => $topicsFailed,
                    'pending' => $topicsPending,
                    'discovered_24h' => $discovered24h,
                    'discovered_7d' => $discovered7d,
                ],
                'articles' => [
                    'published' => $articlesPublished,
                    'draft' => $articlesDraft,
                    'last_24h' => $articles24h,
                    'last_7d' => $articles7d,
                    'last_30d' => $articles30d,
                    'avg_word_count' => round($avgWordCount, 0),
                    'avg_generation_seconds' => round($avgGenDuration, 0),
                    'throughput_per_hour' => $throughput24h,
                ],
                'quality' => [
                    'passed' => $qualityPassed,
                    'failed' => $qualityFailed,
                ],
                'models' => $modelUsage,
                'top_categories' => $topCategories,
                'images' => [
                    'total_with_images' => $articlesWithImages,
                    'ai_generated' => $aiImages,
                    'from_sources' => $sourceImages,
                ],
                'last_24h' => [
                    'generated' => NewsTopic::where('generation_status', 'generated')->where('updated_at', '>=', $last24h)->count(),
                    'failed' => NewsTopic::where('generation_status', 'failed')->where('updated_at', '>=', $last24h)->count(),
                    'success_rate' => ($articles24h > 0)
                        ? round(($articles24h / ($articles24h + NewsTopic::where('generation_status', 'failed')->where('updated_at', '>=', $last24h)->count())) * 100, 1)
                        : 0,
                ],
                'queue' => DB::table('jobs')->count(),
            ],
        ]);
    }
}
