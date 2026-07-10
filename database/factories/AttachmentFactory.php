<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.png';

        return [
            'card_id' => Card::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'attachments/'.Str::random(20).'.png',
            'name' => $name,
            'mime_type' => 'image/png',
            'size' => fake()->numberBetween(10_000, 5_000_000),
        ];
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'path' => 'attachments/'.Str::random(20).'.mp4',
            'name' => fake()->word().'.mp4',
            'mime_type' => 'video/mp4',
        ]);
    }
}
