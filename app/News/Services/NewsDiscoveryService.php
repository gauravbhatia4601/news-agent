<?php

namespace App\News\Services;

use App\News\DTO\DiscoveredSource;
use App\News\DTO\DiscoveredTopic;
use App\News\Repositories\NewsTopicRepository;
use App\News\Sources\Contracts\NewsSource;
use App\News\Sources\GoogleNewsRssSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class NewsDiscoveryService
{
    public function __construct(private readonly NewsTopicRepository $repository)
    {
    }

    /**
     * @param  string[]  $categories
     * @return array<string, DiscoveredTopic[]>
     */
    public function discover(
        array $categories,
        int $limit,
        int $freshHours,
        int $sourcesPerTopic,
    ): array {
        $freshThreshold = now()->subHours($freshHours);
        $cacheKey = (string) config('news-engine.discovery.seen_cache_key', 'news-engine:rss:seen-signatures');
        $cacheTtlSeconds = (int) config('news-engine.discovery.seen_cache_ttl_seconds', 172800);

        $seenSignatures = Cache::get($cacheKey, []);
        if (! is_array($seenSignatures)) {
            $seenSignatures = [];
        }

        $nowTs = now()->timestamp;
        $seenSignatures = array_filter(
            $seenSignatures,
            fn ($timestamp) => is_int($timestamp) && $timestamp >= ($nowTs - $cacheTtlSeconds)
        );

        $results = [];
        $sources = $this->resolveSources();

        foreach ($categories as $category) {
            $candidates = [];
            $fetchLimit = max($limit * 12, 40);

            foreach ($sources as $source) {
                $rows = $source->fetch($category, $freshThreshold, $fetchLimit);

                foreach ($rows as $row) {
                    if (isset($seenSignatures[$row['signature']])) {
                        continue;
                    }

                    $candidates[] = $row;
                }
            }

            if ($candidates === []) {
                $results[$category] = [];
                continue;
            }

            usort($candidates, fn ($a, $b) => ($b['published_at']?->timestamp ?? 0) <=> ($a['published_at']?->timestamp ?? 0));

            $topics = $this->clusterCandidates($category, $candidates, $limit, $sourcesPerTopic);
            if ($topics === []) {
                // Fallback for low-volume runs: slightly relax clustering thresholds
                // so we can still form multi-source topics when the feed is sparse.
                $topics = $this->clusterCandidates($category, $candidates, $limit, $sourcesPerTopic, true);
            }
            
            foreach ($topics as $topic) {
                $this->repository->saveTopicWithSources($topic);

                foreach ($topic->sources as $source) {
                    $seenSignatures[$source->signature] = $nowTs;
                }
            }

            $results[$category] = $topics;
        }

        Cache::put($cacheKey, $seenSignatures, now()->addSeconds($cacheTtlSeconds));

        return $results;
    }

    /**
     * @param  array<int, array{
     *   headline: string,
     *   summary: string,
     *   source_name: string,
     *   source_url: string,
     *   published_at: ?Carbon,
     *   signature: string,
     *   tokens: array<int, string>
     * }>  $candidates
     * @return DiscoveredTopic[]
     */
    private function clusterCandidates(
        string $category,
        array $candidates,
        int $limit,
        int $sourcesPerTopic,
        bool $relaxed = false,
    ): array
    {
        $clusters = [];

        foreach ($candidates as $candidate) {
            $bestIndex = null;
            $bestScore = 0.0;

            foreach ($clusters as $i => $cluster) {
                $intersectionCount = count(array_intersect($candidate['tokens'], $cluster['tokens']));
                $unionCount = count(array_unique(array_merge($candidate['tokens'], $cluster['tokens'])));
                $score = $unionCount > 0 ? ($intersectionCount / $unionCount) : 0.0;

                $minIntersection = $relaxed ? 1 : 2;
                if ($intersectionCount >= $minIntersection && $score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $i;
                }
            }

            $minScore = $relaxed ? 0.2 : 0.35;
            if ($bestIndex !== null && $bestScore >= $minScore) {
                $clusters[$bestIndex]['items'][] = $candidate;
                $clusters[$bestIndex]['tokens'] = array_values(
                    array_unique(array_merge($clusters[$bestIndex]['tokens'], $candidate['tokens']))
                );
                continue;
            }

            $clusters[] = [
                'topic' => $candidate['headline'],
                'tokens' => $candidate['tokens'],
                'items' => [$candidate],
            ];
        }

        usort($clusters, function ($a, $b) {
            $byItemCount = count($b['items']) <=> count($a['items']);
            if ($byItemCount !== 0) {
                return $byItemCount;
            }

            $aLatest = max(array_map(fn ($item) => $item['published_at']?->timestamp ?? 0, $a['items']));
            $bLatest = max(array_map(fn ($item) => $item['published_at']?->timestamp ?? 0, $b['items']));

            return $bLatest <=> $aLatest;
        });

        $topics = [];

        foreach ($clusters as $cluster) {
            if (count($topics) >= $limit) {
                break;
            }

            $uniqueBySource = [];
            foreach ($cluster['items'] as $item) {
                $sourceKey = Str::lower((string) $item['source_name']);
                if (! isset($uniqueBySource[$sourceKey])) {
                    $uniqueBySource[$sourceKey] = $item;
                }
            }

            $sourceRows = array_slice(array_values($uniqueBySource), 0, $sourcesPerTopic);
            if (count($sourceRows) < 2) {
                continue;
            }

            $topicTokens = $cluster['tokens'];
            sort($topicTokens);
            $topicSignature = sha1(Str::lower($category).'|'.implode('|', $topicTokens));

            $sources = array_map(function ($row) {
                return new DiscoveredSource(
                    sourceName: $row['source_name'],
                    sourceUrl: $row['source_url'],
                    headline: $row['headline'],
                    summary: Str::limit((string) $row['summary'], 500),
                    publishedAt: $row['published_at'],
                    signature: $row['signature'],
                );
            }, $sourceRows);

            $topics[] = new DiscoveredTopic(
                category: $category,
                name: $cluster['topic'],
                signature: $topicSignature,
                sources: $sources,
            );
        }

        return $topics;
    }

    /**
     * @return NewsSource[]
     */
    private function resolveSources(): array
    {
        $enabledSources = config('news-engine.sources.enabled', ['google_rss']);
        $enabledSources = is_array($enabledSources) ? $enabledSources : ['google_rss'];

        $resolved = [];

        foreach ($enabledSources as $sourceName) {
            $source = match ($sourceName) {
                'google_rss' => new GoogleNewsRssSource(config('news-engine.sources.google_rss', [])),
                default => throw new RuntimeException("Unknown news source [{$sourceName}] configured."),
            };

            $resolved[] = $source;
        }

        return $resolved;
    }
}
