<?php

namespace App\News\Sources\Contracts;

use Illuminate\Support\Carbon;

interface NewsSource
{
    public function name(): string;

    /**
     * @param  string  $freshnessWindow  Google RSS `when:` value (e.g. '1h', '1d').
     * @param  string  $freshnessOverride  Brave freshness value (e.g. 'ph', 'pd').
     * @return array<int, array{
     *   headline: string,
     *   summary: string,
     *   source_name: string,
     *   source_url: string,
     *   published_at: ?Carbon,
     *   signature: string,
     *   tokens: array<int, string>
     * }>
     */
    public function fetch(
        string $category,
        Carbon $freshThreshold,
        int $perCategoryFetchLimit,
        string $scope = 'india',
        ?string $freshnessWindow = null,
        ?string $freshnessOverride = null,
    ): array;
}
