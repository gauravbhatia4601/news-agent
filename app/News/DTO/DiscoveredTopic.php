<?php

namespace App\News\DTO;

class DiscoveredTopic
{
    /**
     * @param  DiscoveredSource[]  $sources
     */
    public function __construct(
        public readonly string $category,
        public readonly string $name,
        public readonly string $signature,
        public readonly array $sources,
    ) {}

    public function sourceCount(): int
    {
        return count($this->sources);
    }
}
