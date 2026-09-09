<?php

namespace App\News\DTO;

class DiscoveredTopic
{
    /**
     * Persisted row id, set by the repository after saving. Null until saved.
     */
    public ?int $persistedId = null;

    /**
     * @param  DiscoveredSource[]  $sources
     * @param  string[]  $coreTokens
     */
    public function __construct(
        public readonly string $category,
        public readonly string $name = '',
        public readonly string $signature = '',
        public readonly array $sources = [],
        public readonly ?int $categoryId = null,
        public readonly array $coreTokens = [],
        public readonly ?int $locationCategoryId = null,
    ) {}

    public function sourceCount(): int
    {
        return count($this->sources);
    }
}
