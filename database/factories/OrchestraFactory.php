<?php

namespace Database\Factories;

use App\Models\Orchestra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Orchestra>
 */
class OrchestraFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->randomNumber(5),
            'name' => fake()->words(2, true),
            'abbreviation' => strtoupper(fake()->lexify('???')),
            'type' => fake()->randomElement(['orkest', 'ensemble', 'groep']),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
