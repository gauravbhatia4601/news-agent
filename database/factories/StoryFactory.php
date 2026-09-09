<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Story>
 */
class StoryFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'description' => fake()->optional()->paragraph(),
            'search_query' => $title,
            'category_id' => null,
            'urgency' => 'developing',
            'status' => 'auto-detected',
            'started_at' => now(),
            'concluded_at' => null,
            'last_monitored_at' => null,
            'monitor_interval_minutes' => 10,
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attrs) => [
            'urgency' => 'live',
        ]);
    }

    public function concluded(): static
    {
        return $this->state(fn (array $attrs) => [
            'urgency' => 'concluded',
            'concluded_at' => now(),
        ]);
    }
}
