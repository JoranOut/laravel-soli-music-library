<?php

namespace Database\Factories;

use App\Models\Piece;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Piece>
 */
class PieceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'composer' => fake()->name(),
            'arranger' => fake()->optional(0.5)->name(),
            'publisher' => fake()->optional(0.3)->company(),
            'difficulty' => fake()->optional(0.7)->randomElement(['easy', 'medium', 'hard', 'very hard']),
            'notes' => fake()->optional(0.3)->sentence(),
            'bought_for' => fake()->optional(0.4)->randomElement(['Harmonie orkest', 'Klein Orkest', 'Bigband', 'Slagwerkgroep']),
            'buy_date' => fake()->optional(0.4)->dateTimeBetween('-10 years', 'now'),
            'genre' => fake()->optional(0.5)->randomElements(
                ['Pop', 'Filmmuziek', 'Klassiek', 'Jazz', 'Musical', 'Kerst', 'Overture', 'Wals'],
                fake()->numberBetween(1, 3),
            ),
            'music_type' => fake()->optional(0.5)->randomElement(['Concert', 'Loopmars', 'Slagwerk', 'Divers']),
            'archive_number' => fake()->optional(0.4)->numberBetween(1, 999),
            'status' => fake()->randomElement(['besteld', 'analoog', 'digitaal']),
            'audio_youtube_url' => fake()->optional(0.2)->randomElement([
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'https://www.youtube.com/watch?v=9bZkp7q19f0',
                'https://www.youtube.com/watch?v=kJQP7kiw5Fk',
            ]),
        ];
    }
}
