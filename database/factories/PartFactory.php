<?php

namespace Database\Factories;

use App\Models\InstrumentType;
use App\Models\Part;
use App\Models\Piece;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    public function definition(): array
    {
        $instrumentName = fake()->word();

        return [
            'piece_id' => Piece::factory(),
            'instrument_type_id' => InstrumentType::factory(),
            'is_conductor' => false,
            'voice' => null,
            'file_path' => 'parts/'.fake()->uuid().'.pdf',
            'original_filename' => $instrumentName.'.pdf',
        ];
    }

    public function partituur(): static
    {
        return $this->state(fn () => [
            'is_conductor' => true,
            'original_filename' => 'partituur.pdf',
        ]);
    }

    public function fileless(): static
    {
        return $this->state(fn () => [
            'file_path' => null,
            'original_filename' => null,
            'amount_bought' => fake()->numberBetween(1, 10),
        ]);
    }
}
