<?php

namespace App\Ai\Services;

class EntityExtractionResult
{
    public function __construct(
        public readonly array $people,
        public readonly array $organizations,
        public readonly array $locations,
        public readonly array $dates,
        public readonly array $numbers,
        public readonly array $primaryTopics,
        public readonly array $searchQuestions,
        public readonly string $topicTerm,
    ) {}

    public function toPromptContext(): string
    {
        $lines = [];

        if ($this->topicTerm !== '') {
            $lines[] = "PRIMARY TOPIC: {$this->topicTerm}";
        }

        if ($this->people !== []) {
            $lines[] = 'Key People Mentioned: '.implode(', ', $this->people);
        }

        if ($this->organizations !== []) {
            $lines[] = 'Organizations & Institutions: '.implode(', ', $this->organizations);
        }

        if ($this->locations !== []) {
            $lines[] = 'Locations: '.implode(', ', $this->locations);
        }

        if ($this->dates !== []) {
            $lines[] = 'Dates Referenced: '.implode(', ', $this->dates);
        }

        if ($this->numbers !== []) {
            $lines[] = 'Key Figures & Statistics: '.implode(', ', $this->numbers);
        }

        if ($this->primaryTopics !== []) {
            $lines[] = 'Topic Keywords: '.implode(', ', $this->primaryTopics);
        }

        if ($this->searchQuestions !== []) {
            $lines[] = 'Reader Questions to Address: '.implode('; ', $this->searchQuestions);
        }

        return implode("\n", $lines);
    }
}
