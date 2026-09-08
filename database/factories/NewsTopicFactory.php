<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NewsTopic>
 */
class NewsTopicFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => fake()->word(),
            'topic_name' => fake()->sentence(),
            'topic_signature' => 'sig-'.Str::random(12),
            'source_count' => 1,
            'generation_status' => 'pending',
        ];
    }
}
