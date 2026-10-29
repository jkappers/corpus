<?php

namespace Database\Factories;

use App\Enums\SpaceRole;
use App\Enums\SpaceVisibility;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'visibility' => SpaceVisibility::Open,
            'owner_id' => null,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['visibility' => SpaceVisibility::Private]);
    }

    public function visible(): static
    {
        return $this->state(fn () => ['visibility' => SpaceVisibility::Visible]);
    }

    /**
     * @param  iterable<User>  $users
     */
    public function withMembers(iterable $users, SpaceRole $role = SpaceRole::Editor): static
    {
        return $this->afterCreating(function (Space $space) use ($users, $role) {
            foreach ($users as $user) {
                $space->users()->attach($user, ['role' => $role->value]);
            }
        });
    }
}
