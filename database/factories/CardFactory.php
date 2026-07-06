<?php

namespace Database\Factories;

use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_list_id' => BoardList::factory(),
            'board_id' => fn (array $attributes) => BoardList::find($attributes['board_list_id'])->board_id,
            'created_by' => User::factory(),
            'title' => Str::of(fake()->sentence(4))->rtrim('.'),
            'description' => fake()->optional()->paragraph(),
            'position' => 0,
        ];
    }
}
