<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Board;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'card_id' => null,
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['card.created', 'card.moved', 'card.updated', 'comment.created']),
            'properties' => [],
        ];
    }
}
