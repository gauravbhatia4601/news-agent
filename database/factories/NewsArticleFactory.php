<?php

namespace Database\Factories;

use App\Models\NewsTopic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NewsArticle>
 */
class NewsArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'topic_id' => NewsTopic::factory(),
            'title' => fake()->sentence(),
            'content' => '<p>'.fake()->paragraph(10).'</p>',
            'slug' => fn (array $attrs) => Str::slug($attrs['title']).'-'.Str::random(6),
            'status' => 'published',
            'meta_title' => fake()->sentence(),
            'meta_description' => fake()->text(160),
            'meta_keywords' => implode(', ', fake()->words(10)),
            'provider' => 'test',
            'model' => 'test-model',
            'metadata' => [],
        ];
    }
}
