<?php

namespace Database\Factories;

use App\Enums\PageType;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'space_id' => fn (array $attributes) => User::findOrFail($attributes['user_id'])->personalSpace->id,
            'type' => PageType::Page,
            'title' => fake()->sentence(3),
            'body' => '# '.fake()->sentence(3)."\n\n".fake()->paragraph(),
            'data' => null,
        ];
    }

    public function board(): static
    {
        return $this->state(fn () => [
            'type' => PageType::Board,
            'body' => null,
            'data' => [
                'nodes' => [],
                'edges' => [],
                'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
            ],
        ]);
    }
}
