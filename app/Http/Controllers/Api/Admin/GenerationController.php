<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Models\QueueJobLog;
use App\News\Sources\BraveSearchSource;
use App\News\Sources\GoogleNewsRssSource;
use App\Services\AuditLogService;
use App\Services\SitemapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class GenerationController extends Controller
{
    public function queueStatus(): JsonResponse
    {
        $now = now();
        $connection = config('queue.default', 'database');
        $queueName = config("queue.connections.{$connection}.queue", 'default');

        $pendingJobs = Queue::size($queueName);
        $totalFailedJobs = DB::table('failed_jobs')->count();

        $pendingDetails = $connection === 'redis'
            ? $this->pendingJobsFromRedis($queueName)
            : $this->pendingJobsFromDatabase($queueName);

        $recentFailed = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->take(20)
            ->get()
            ->map(fn ($j) => $this->serializeJob($j, 'failed'));

        $workerStatus = $this->detectWorkerStatus($pendingJobs, $totalFailedJobs, $pendingDetails);

        $lastRunIndia = Cache::get('news-engine:last-discovery-run:india');
        $lastRunGlobal = Cache::get('news-engine:last-discovery-run:global');
        $lastRun = collect([$lastRunIndia, $lastRunGlobal])->filter()->sortDesc()->first();
        $nextRun = $this->calculateNextDiscoveryRun($lastRun);

        $articlesLastHour = NewsArticle::where('created_at', '>=', $now->clone()->subHour())->count();
        $articlesLast24h = NewsArticle::where('created_at', '>=', $now->clone()->subHours(24))->count();

        return response()->json([
            'data' => [
                'queue' => [
                    'pending_jobs' => $pendingJobs,
                    'total_failed_jobs' => $totalFailedJobs,
                    'worker_status' => $workerStatus,
                    'pending_jobs_list' => $pendingDetails,
                    'recent_failed_jobs' => $recentFailed,
                    'connection' => $connection,
                    'queue_name' => $queueName,
                ],
                'sources' => [
                    'google_rss_hits' => (int) Cache::get(GoogleNewsRssSource::HIT_CACHE_KEY, 0),
                    'brave_search_hits' => (int) Cache::get(BraveSearchSource::HIT_CACHE_KEY, 0),
                    'brave_search_enabled' => (bool) config('news-engine.sources.brave_search.enabled', true),
                    'brave_fallback_threshold' => (int) config('news-engine.discovery.brave_fallback_threshold', 8),
                ],
                'discovery' => [
                    'last_run_at' => $lastRun?->toIso8601String(),
                    'last_run_at_india' => $lastRunIndia?->toIso8601String(),
                    'last_run_at_global' => $lastRunGlobal?->toIso8601String(),
                    'next_run_at' => $nextRun?->toIso8601String(),
                    'next_run_in_seconds' => $lastRun ? max(0, (int) $nextRun?->diffInSeconds($now, false)) : null,
                    'command' => 'news:discover --queue',
                    'command_global' => 'news:discover --queue --scope=global',
                    'frequency' => 'hourly (India at :00, Global at :30)',
                    'default_limit' => (int) config('news-engine.discovery.default_limit', 3),
                    'default_sources_per_topic' => (int) config('news-engine.discovery.default_sources_per_topic', 5),
                ],
                'generation' => [
                    'model' => \App\Models\Setting::get('generation.model') ?? (string) config('news-engine.generation.model', 'gemma4:31b-cloud'),
                    'provider' => \App\Models\Setting::get('generation.provider') ?? (string) config('news-engine.generation.provider', 'ollama'),
                    'enabled' => \App\Models\Setting::get('generation.enabled') !== null
                        ? (bool) \App\Models\Setting::get('generation.enabled')
                        : (bool) config('news-engine.generation.enabled', true),
                    'articles_last_hour' => $articlesLastHour,
                    'articles_last_24h' => $articlesLast24h,
                    'avg_generation_seconds' => round(
                        NewsArticle::where('created_at', '>=', $now->clone()->subHours(24))
                            ->where('generation_duration_seconds', '>', 0)
                            ->selectRaw('AVG(generation_duration_seconds) as avg_seconds')
                            ->value('avg_seconds') ?? 0,
                        1
                    ),
                    'pending_topics' => NewsTopic::where('generation_status', 'pending')->count(),
                    'generated_topics' => NewsTopic::where('generation_status', 'generated')->count(),
                    'failed_topics' => NewsTopic::where('generation_status', 'failed')->count(),
                ],
                'timestamp' => $now->toIso8601String(),
            ],
        ]);
    }

    private function pendingJobsFromDatabase(string $queueName): array
    {
        return DB::table('jobs')
            ->where('queue', $queueName)
            ->orderByDesc('available_at')
            ->take(50)
            ->get()
            ->map(fn ($j) => $this->serializeJob($j, 'pending'))
            ->all();
    }

    private function pendingJobsFromRedis(string $queueName): array
    {
        $redis = Redis::connection(config('queue.connections.redis.connection', 'default'));

        $waitingKey = 'queues:'.$queueName;
        $reservedKey = $waitingKey.':reserved';
        $delayedKey = $waitingKey.':delayed';

        $jobs = [];

        // Waiting jobs
        foreach ($redis->lrange($waitingKey, 0, 49) as $index => $payload) {
            $job = $this->parseRedisPayload($payload, $queueName, 'waiting', $index);
            if ($job) {
                $jobs[] = $job;
            }
        }

        // Jobs currently reserved/processing by a worker
        $reserved = $redis->zrange($reservedKey, 0, 49, 'WITHSCORES');
        foreach ($reserved as $payload => $score) {
            $job = $this->parseRedisPayload($payload, $queueName, 'processing');
            if ($job) {
                $jobs[] = $job;
            }
        }

        // Delayed jobs (scheduled for later)
        $delayed = $redis->zrange($delayedKey, 0, 49, 'WITHSCORES');
        foreach ($delayed as $payload => $score) {
            $job = $this->parseRedisPayload($payload, $queueName, 'delayed');
            if ($job) {
                $jobs[] = $job;
            }
        }

        return array_slice($jobs, 0, 50);
    }

    private function parseRedisPayload(string $payload, string $queueName, string $state, ?int $index = null): ?array
    {
        $job = json_decode($payload, true);
        if (! is_array($job)) {
            return null;
        }

        $commandName = $this->resolveCommandName($job);
        $id = $job['uuid'] ?? ($job['id'] ?? 'redis-'.($index ?? substr(sha1($payload), 0, 8)));

        $stateLabels = [
            'waiting' => 'Queued in Redis',
            'processing' => 'Processing by worker',
            'delayed' => 'Delayed / scheduled',
        ];

        return [
            'id' => $id,
            'queue' => $queueName,
            'command' => $commandName,
            'type' => 'pending',
            'state' => $state,
            'available_at' => null,
            'available_at_label' => $stateLabels[$state] ?? 'Queued in Redis',
            'attempts' => (int) ($job['attempts'] ?? 0),
        ];
    }

    private function resolveCommandName(array $job): ?string
    {
        $displayName = $job['displayName'] ?? null;
        $commandName = is_string($displayName) ? $displayName : null;

        $command = $job['data']['command'] ?? null;
        if (is_string($command)) {
            $unserialized = @unserialize($command);
            if ($unserialized instanceof \App\Jobs\GenerateArticle) {
                $commandName = 'GenerateArticle: '.$unserialized->topicSignature;
            }
        }

        return $commandName;
    }

    private function serializeJob(object $job, string $type): array
    {
        $commandName = null;
        $payload = json_decode($job->payload ?? '{}', true);

        if (is_array($payload)) {
            $commandName = $this->resolveCommandName($payload);
        }

        $base = [
            'id' => $job->uuid ?? $job->id,
            'queue' => $job->queue,
            'command' => $commandName,
            'type' => $type,
        ];

        if ($type === 'pending') {
            $base['available_at'] = $this->formatDate($job->available_at);
            $base['attempts'] = (int) $job->attempts;
        } else {
            $base['failed_at'] = $this->formatDate($job->failed_at);
            $base['exception'] = $job->exception;
            $base['exception_preview'] = Str::limit($job->exception, 300);
        }

        return $base;
    }

    private function detectWorkerStatus(int $pendingJobs, int $totalFailedJobs, $pendingDetails): array
    {
        $heartbeat = Cache::get('news-engine:worker-heartbeat');
        $running = $heartbeat && $heartbeat->diffInMinutes(now(), false) < 5;

        $details = $running
            ? 'Queue worker active (heartbeat within last 5 minutes).'
            : 'No recent worker heartbeat detected. Worker may be idle or stopped.';

        $oldestPending = collect($pendingDetails)->last();
        $stalled = false;
        if ($oldestPending && ! empty($oldestPending['available_at'])) {
            $availableAt = Carbon::parse($oldestPending['available_at']);
            $stalled = $availableAt->diffInMinutes(now(), false) > 15;
        }

        return [
            'queue_work_running' => $running,
            'stalled' => $stalled,
            'details' => $details,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $totalFailedJobs,
        ];
    }

    private function calculateNextDiscoveryRun(?Carbon $lastRun): ?Carbon
    {
        if (! $lastRun instanceof Carbon) {
            return null;
        }

        $next = $lastRun->clone()->addHour()->startOfHour();

        return $next->isPast() ? $next->clone()->addHour() : $next;
    }

    private function formatDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return is_string($value) ? $value : null;
        }
    }

    public function queueHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,processing,processed,failed,released',
            'per_page' => 'nullable|integer|min:10|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        $status = $validated['status'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 50);
        $page = (int) ($validated['page'] ?? 1);

        $query = QueueJobLog::query()
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'jobs' => $paginated->items(),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
            ],
        ]);
    }

    public function regenerateSitemap(): JsonResponse
    {
        $service = new SitemapService;
        $files = $service->generate();

        AuditLogService::log('regenerate_sitemap', 'Sitemap');

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
                'queue' => Queue::size(config('queue.connections.'.config('queue.default', 'database').'.queue', 'default')),
            ],
        ]);
    }

    public function listSitemaps(): JsonResponse
    {
        $dir = public_path('sitemaps');
        $files = [];

        if (is_dir($dir)) {
            foreach (glob($dir.'/*.xml') as $file) {
                $name = basename($file);
                $size = filesize($file);
                $modified = filemtime($file);
                $files[] = [
                    'name' => $name,
                    'size' => $size,
                    'size_human' => $size > 1024 ? round($size / 1024, 1).' KB' : $size.' B',
                    'modified_at' => date('c', $modified),
                    'url' => rtrim(config('app.frontend_url') ?: config('app.url'), '/').'/sitemaps/'.$name,
                    'preview' => file_get_contents($file),
                ];
            }
        }

        usort($files, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json(['data' => $files]);
    }

    public function showSitemap(string $name): JsonResponse
    {
        $path = public_path('sitemaps/'.basename($name));

        if (! file_exists($path) || ! str_ends_with($path, '.xml')) {
            abort(404, 'Sitemap not found');
        }

        return response()->json([
            'data' => [
                'name' => basename($path),
                'size' => filesize($path),
                'size_human' => filesize($path) > 1024 ? round(filesize($path) / 1024, 1).' KB' : filesize($path).' B',
                'modified_at' => date('c', filemtime($path)),
                'url' => rtrim(config('app.frontend_url') ?: config('app.url'), '/').'/sitemaps/'.basename($path),
                'content' => file_get_contents($path),
            ],
        ]);
    }
}
