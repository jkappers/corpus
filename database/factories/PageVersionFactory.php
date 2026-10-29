<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageVersion>
 */
class PageVersionFactory extends Factory
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
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'reason' => 'autosave',
        ];
    }
}
