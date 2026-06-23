<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class NewsletterSubscriberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'source' => 'website',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'consent_text' => 'User subscribed via website form',
            'consent_at' => now(),
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ];
    }
}