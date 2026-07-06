<?php

namespace Database\Factories;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_id' => Checklist::factory(),
            'content' => Str::of(fake()->sentence(3))->rtrim('.'),
            'is_completed' => fake()->boolean(30),
            'position' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['is_completed' => true]);
    }
}
