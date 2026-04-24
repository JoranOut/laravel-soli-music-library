<?php

namespace Database\Factories;

use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentType>
 */
class InstrumentTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->randomNumber(5),
            'name' => fake()->word(),
            'instrument_family_id' => InstrumentFamily::factory(),
            'sort_order' => fake()->numberBetween(0, 10),
            'aliases' => [],
        ];
    }
}
