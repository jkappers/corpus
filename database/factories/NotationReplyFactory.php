<?php

namespace Database\Factories;

use App\Models\Notation;
use App\Models\NotationReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotationReply>
 */
class NotationReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notation_id' => Notation::factory(),
            'author_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
