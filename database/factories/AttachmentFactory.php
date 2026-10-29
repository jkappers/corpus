<?php

namespace Database\Factories;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.png';

        return [
            'page_id' => Page::factory(),
            'user_id' => User::factory(),
            'kind' => AttachmentKind::Image,
            'disk' => config('filesystems.attachments_disk'),
            'path' => 'attachments/'.fake()->uuid().'.png',
            'original_name' => $name,
            'mime_type' => 'image/png',
            'size' => fake()->numberBetween(1_000, 500_000),
        ];
    }

    public function file(): static
    {
        return $this->state(fn () => [
            'kind' => AttachmentKind::File,
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }
}
