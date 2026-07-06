<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'name' => fake()->randomElement(['Urgent', 'Bug', 'Feature', 'Design', 'Backend', 'Idée']),
            'color' => fake()->randomElement(['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#ec4899']),
        ];
    }
}
