<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryEntry>
 */
class LibraryEntryFactory extends Factory
{
    /**
     * Define the model's default state: played, but not recently.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'playtime_minutes' => fake()->numberBetween(1, 30_000),
            'playtime_2weeks_minutes' => null,
            'last_played_at' => fake()->dateTimeBetween('-3 years', '-1 month'),
        ];
    }

    /**
     * Owned but never played.
     */
    public function unplayed(): static
    {
        return $this->state(fn (array $attributes) => [
            'playtime_minutes' => 0,
            'playtime_2weeks_minutes' => null,
            'last_played_at' => null,
        ]);
    }

    /**
     * Played within the last two weeks.
     */
    public function recent(): static
    {
        return $this->state(function (array $attributes) {
            $recent = fake()->numberBetween(5, 1_500);

            return [
                'playtime_minutes' => $recent + fake()->numberBetween(0, 20_000),
                'playtime_2weeks_minutes' => $recent,
                'last_played_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
            ];
        });
    }
}
