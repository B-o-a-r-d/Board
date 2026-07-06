<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Checklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checklist>
 */
class ChecklistFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'title' => fake()->randomElement(['Checklist', 'Tâches', 'Critères d\'acceptation']),
            'position' => 0,
        ];
    }
}
