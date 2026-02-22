<?php

namespace App\News\Sources\Contracts;

use Illuminate\Support\Carbon;

interface NewsSource
{
    public function name(): string;

    /**
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
    public function fetch(string $category, Carbon $freshThreshold, int $perCategoryFetchLimit): array;
}
