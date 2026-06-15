<?php

namespace App\News\Services;

use App\News\DTO\DiscoveredSource;
use App\News\DTO\DiscoveredTopic;
use App\News\Repositories\NewsTopicRepository;
use App\News\Sources\Contracts\NewsSource;
use App\News\Sources\BraveSearchSource;
use App\News\Sources\GoogleNewsRssSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class NewsDiscoveryService
{
    public function __construct(
        private readonly NewsTopicRepository $repository,
        private readonly TopicCategoryDetectionService $topicDetection,
    ) {
    }

    /**
     * @param  array<int, array{slug: string, name: string, category_id: int}>  $locations
     * @return DiscoveredTopic[]
     */
    public function discover(
        array $locations,
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

        $allTopics = [];
        $sources = $this->resolveSources();

        foreach ($locations as $location) {
            $candidates = [];
            $fetchLimit = max($limit * 12, 40);
            $locationSlug = $location['slug'];

            foreach ($sources as $source) {
                $rows = $source->fetch($locationSlug, $freshThreshold, $fetchLimit);

                foreach ($rows as $row) {
                    if (isset($seenSignatures[$row['signature']])) {
                        continue;
                    }

                    $candidates[] = $row;
                }
            }

            if ($candidates === []) {
                continue;
            }

            usort($candidates, fn ($a, $b) => ($b['published_at']?->timestamp ?? 0) <=> ($a['published_at']?->timestamp ?? 0));

            $topics = $this->clusterCandidates($location, $candidates, $limit, $sourcesPerTopic);
            if ($topics === []) {
                $topics = $this->clusterCandidates($location, $candidates, $limit, $sourcesPerTopic, true);
            }

            foreach ($topics as $topic) {
                $this->repository->saveTopicWithSources($topic);

                foreach ($topic->sources as $source) {
                    $seenSignatures[$source->signature] = $nowTs;
                }

                $allTopics[] = $topic;
            }
        }

        Cache::put($cacheKey, $seenSignatures, now()->addSeconds($cacheTtlSeconds));

        return $allTopics;
    }

    /**
     * @param  array{slug: string, name: string, category_id: int}  $location
     * @param  array<int, array>  $candidates
     * @return DiscoveredTopic[]
     */
    private function clusterCandidates(
        array $location,
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

            $allHeadlineTokens = array_map(fn ($item) => $item['tokens'], $cluster['items']);
            $tokenCounts = [];
            foreach ($allHeadlineTokens as $tokens) {
                foreach ($tokens as $token) {
                    $tokenCounts[$token] = ($tokenCounts[$token] ?? 0) + 1;
                }
            }
            $coreTokens = array_keys(array_filter($tokenCounts, fn ($count) => $count >= 2));
            sort($coreTokens);

            $topicSignature = $coreTokens !== []
                ? sha1(Str::lower($location['slug']).'|'.implode('|', $coreTokens))
                : sha1(Str::lower($location['slug']).'|'.implode('|', $cluster['tokens']));

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

            $sourceData = array_map(fn ($s) => [
                'headline' => $s->headline,
                'summary' => $s->summary,
            ], $sources);

            $topicCategory = $this->topicDetection->detect($cluster['topic'], $sourceData);

            if ($topicCategory['id'] === null) {
                continue;
            }

            $topics[] = new DiscoveredTopic(
                category: $topicCategory['name'],
                name: $cluster['topic'],
                signature: $topicSignature,
                sources: $sources,
                categoryId: $topicCategory['id'],
                coreTokens: $coreTokens,
                locationCategoryId: $location['category_id'],
            );
        }

        return $topics;
    }

    /**
     * @return NewsSource[]
     */
    private function resolveSources(): array
    {
        $enabledSources = config('news-engine.sources.enabled', ['google_rss', 'brave_search']);
        $enabledSources = is_array($enabledSources) ? $enabledSources : ['google_rss', 'brave_search'];

        $resolved = [];

        foreach ($enabledSources as $sourceName) {
            $source = match ($sourceName) {
                'google_rss' => new GoogleNewsRssSource(config('news-engine.sources.google_rss', [])),
                'brave_search' => new BraveSearchSource(config('news-engine.sources.brave_search', [])),
                default => throw new RuntimeException("Unknown news source [{$sourceName}] configured."),
            };

            $resolved[] = $source;
        }

        return $resolved;
    }
}
