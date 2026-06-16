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
        string $scope = 'india',
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
        $googleSource = $this->resolveGoogleSource();
        $braveSource = $this->resolveBraveSource();
        $braveFallbackThreshold = (int) config('news-engine.discovery.brave_fallback_threshold', 12);

        foreach ($locations as $location) {
            $fetchLimit = max($limit * 12, 40);
            $locationSlug = $location['slug'];

            $candidates = $this->fetchFromSource(
                $googleSource,
                $locationSlug,
                $freshThreshold,
                $fetchLimit,
                $seenSignatures,
                $scope,
            );

            // Fall back to Brave only if Google RSS didn't return enough fresh candidates.
            if ($braveSource !== null && count($candidates) < $braveFallbackThreshold) {
                $braveCandidates = $this->fetchFromSource(
                    $braveSource,
                    $locationSlug,
                    $freshThreshold,
                    $fetchLimit,
                    $seenSignatures,
                    $scope,
                );

                $candidates = array_merge($candidates, $braveCandidates);
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
     * @return array<int, array>
     */
    private function fetchFromSource(
        NewsSource $source,
        string $locationSlug,
        Carbon $freshThreshold,
        int $fetchLimit,
        array $seenSignatures,
        string $scope = 'india',
    ): array {
        $rows = $source->fetch($locationSlug, $freshThreshold, $fetchLimit, $scope);

        $candidates = [];
        foreach ($rows as $row) {
            if (isset($seenSignatures[$row['signature']])) {
                continue;
            }

            $candidates[] = $row;
        }

        return $candidates;
    }

    /**
     * @return NewsSource[]
     * @deprecated Kept for any callers expecting both sources. Discovery now uses Google RSS primary + Brave fallback.
     */
    private function resolveSources(): array
    {
        $sources = [$this->resolveGoogleSource()];

        $brave = $this->resolveBraveSource();
        if ($brave !== null) {
            $sources[] = $brave;
        }

        return $sources;
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

    private function resolveGoogleSource(): GoogleNewsRssSource
    {
        return new GoogleNewsRssSource(config('news-engine.sources.google_rss', []));
    }

    private function resolveBraveSource(): ?BraveSearchSource
    {
        if (! (bool) config('news-engine.sources.brave_search.enabled', true)) {
            return null;
        }

        return new BraveSearchSource(config('news-engine.sources.brave_search', []));
    }
}
