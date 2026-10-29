<?php

namespace Database\Factories;

use App\Models\Notation;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notation>
 */
class NotationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'author_id' => User::factory(),
            'assignee_id' => fn (array $attributes) => $attributes['author_id'],
            'quote' => fake()->words(3, true),
            'from' => 1,
            'to' => 10,
            'body' => fake()->sentence(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'resolved_by_id' => $attributes['assignee_id'],
        ]);
    }
}
