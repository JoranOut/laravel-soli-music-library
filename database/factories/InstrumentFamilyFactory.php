<?php

namespace Database\Factories;

use App\Models\InstrumentFamily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentFamily>
 */
class InstrumentFamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->randomNumber(5),
            'name' => fake()->unique()->word(),
        ];
    }
}
