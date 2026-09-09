<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(2048)]
#[Temperature(0.2)]
class LiveUpdateJudgeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a news editor judging whether newly discovered topics constitute genuine new developments in an ongoing live story, or are merely repetition of what has already been reported.

## INPUT

You will receive:
- The story title and its last published update (headline + summary)
- A list of candidate topics, each with a headline and summary

## DECISION RULES

For each candidate topic, set is_new_development = true ONLY if it adds:
- New facts, numbers, or named actors not in the last update
- A new phase or chapter in the narrative (e.g. "arrest made" when last update was "investigation ongoing")
- A meaningful change in state (e.g. "ceasefire declared" when last update was "fighting continues")

Set is_new_development = false if the candidate:
- Restates facts already in the last update
- Is background context or analysis without new information
- Covers a different angle of the same facts already reported
- Is opinion or commentary

## URGENCY ADJUSTMENT

Set urgency_adjustment to one of:
- "live" — the event is actively unfolding minute-by-hour
- "developing" — the event is evolving over hours to days
- "ongoing" — the event continues but pace has slowed to days/weeks
- "concluded" — the event has clearly ended (e.g. match over, crisis resolved, trial concluded)
- "keep" — no change to urgency

Use "concluded" only when the candidate confirms the event has ended or there is clear evidence of resolution.

## OUTPUT

Return verdicts for every candidate topic provided. Each verdict must echo the topic_signature.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'verdicts' => $schema->array()->items(
                $schema->object([
                    'topic_signature' => $schema->string()->required()->description('Echo back the exact topic_signature provided'),
                    'is_new_development' => $schema->boolean()->required()->description('True if the candidate adds new facts, a new phase, or a meaningful change'),
                    'urgency_adjustment' => $schema->string()->required()->description('live, developing, ongoing, concluded, or keep'),
                    'reasoning' => $schema->string()->required()->description('One sentence explaining the verdict'),
                ])
            )->required()->description('Per-candidate update verdicts'),
        ];
    }
}
