<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class NewsArticleAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return "You are an expert newsroom editor and journalist. Produce accurate, source-grounded, and highly readable reporting.\n\n"
             . "Rules:\n"
             . "- Use accessible language suitable for a general audience (around 8th-grade reading level).\n"
             . "- Stay neutral and factual. No speculation, opinionated framing, or emotional exaggeration.\n"
             . "- Use ONLY the provided sources. Do not invent facts, quotes, entities, dates, or numbers.\n"
             . "- Write a full article in MULTIPLE sections using Markdown headings (## Heading).\n"
             . "- Each section must include 1-3 short paragraphs for readability.\n"
             . "- Target article length: 700-1100 words.\n"
             . "- Start with a strong lead paragraph that explains what happened and why it matters.\n"
             . "- Include context, current developments, practical impact, and what to watch next.\n"
             . "- Attribute key claims to sources using inline citation markers like [1], [2].\n"
             . "- If facts conflict between sources, explicitly mention the disagreement.\n"
             . "- Avoid markdown code fences, emojis, decorative symbols, and filler phrases.\n"
             . "- Generate a realistic-sounding journalist name or 'AI News Desk' for the author.\n"
             . "- Estimate read time in minutes as an integer.\n"
             . "- Write in a way to make it engaging and interesting to read.\n"
             . "- Write it to serve it as a news/information/value to the reader, not as a statement.\n"
             . "- Return clean markdown prose only for the article field.";
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required()->description('An engaging headline.'),
            'article' => $schema->string()->required()->description('Long-form markdown news article with at least 4 sections, multiple paragraphs, and inline citations.'),
            'author' => $schema->string()->required()->description('The author name. Generate a realistic-sounding journalist name or use "AI News Desk".'),
            'read_time_minutes' => $schema->integer()->required()->description('Estimated read time in minutes.'),
            'meta_title' => $schema->string()->required()->description('SEO meta title, concise and clickable, max 60 characters preferred.'),
            'meta_description' => $schema->string()->required()->description('SEO meta description, max 160 characters preferred.'),
            'meta_keywords' => $schema->array()->items($schema->string())->required()->description('A short list of SEO keywords relevant to the article.'),
            'citations' => $schema->array()->items(
                $schema->object([
                    'id' => $schema->integer()->required(),
                    'source_name' => $schema->string()->required(),
                    'source_url' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
