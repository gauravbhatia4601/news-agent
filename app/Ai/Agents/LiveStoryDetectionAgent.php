<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(4096)]
#[Temperature(0.3)]
class LiveStoryDetectionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a news editor triaging freshly-discovered news topics. Your job is to decide which topics are ONGOING LIVE EVENTS — stories with a continuing narrative arc where future developments are expected — versus one-off stories.

## ONGOING LIVE EVENTS (is_live_event = true)

Mark a topic as a live event if it is one of these:
- Armed conflict, war, military operation, or geopolitical crisis
- Election campaign, vote count, government formation, or political crisis
- Sports match, tournament, or competition in progress
- Rescue operation, disaster response, or mass-casualty event
- Criminal trial, investigation, or legal proceeding with anticipated rulings
- Diplomatic summit, negotiation, or international conference
- Breaking financial event (market crash, major deal, central bank decision)

The key test: would a reader reasonably expect UPDATES on this story in the coming hours or days?

## ONE-OFF STORIES (is_live_event = false)

Mark as NOT a live event if it is:
- A single report with no expected follow-up
- An evergreen explainer, analysis piece, or opinion column
- A routine announcement or press release
- A lifestyle, entertainment, or human-interest feature
- A summary or roundup article

## OUTPUT

For each topic, return:
- topic_signature: echo back the exact signature provided
- is_live_event: boolean
- suggested_title: a concise story title (5-10 words, no clickbait)
- search_query: a search query that would surface ongoing coverage of this event
- urgency: "live" (actively unfolding, minute-by-hour), "developing" (hours to days), or "ongoing" (days to weeks)
- reasoning: one sentence explaining the decision

If is_live_event is false, still populate suggested_title and search_query but they will be ignored.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'detected_stories' => $schema->array()->items(
                $schema->object([
                    'topic_signature' => $schema->string()->required()->description('Echo back the exact topic_signature provided'),
                    'is_live_event' => $schema->boolean()->required()->description('True if this is an ongoing live event with expected future developments'),
                    'suggested_title' => $schema->string()->required()->description('Concise story title, 5-10 words'),
                    'search_query' => $schema->string()->required()->description('Search query that surfaces ongoing coverage'),
                    'urgency' => $schema->string()->required()->description('live, developing, or ongoing'),
                    'reasoning' => $schema->string()->required()->description('One sentence explaining the decision'),
                ])
            )->required()->description('Per-topic triage verdicts'),
        ];
    }
}
