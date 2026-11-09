<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state: freshly imported, not yet matched.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'steam_appid' => fake()->unique()->numberBetween(10, 2_500_000),
            'name' => Str::title(rtrim(fake()->sentence(fake()->numberBetween(1, 4)), '.')),
            'icon_hash' => fake()->sha1(),
        ];
    }

    /**
     * Matched to IGDB. Some time-to-beat values are null, as IGDB often lacks them.
     */
    public function matched(): static
    {
        return $this->state(function (array $attributes) {
            $hours = static fn (int $min, int $max, float $chance): ?int => fake()->boolean($chance)
                ? fake()->numberBetween($min, $max) * 3600
                : null;

            return [
                'igdb_id' => fake()->unique()->numberBetween(1, 400_000),
                'slug' => Str::slug(Arr::string($attributes, 'name')),
                'first_release_date' => fake()->dateTimeBetween('-20 years', '-1 month')->format('Y-m-d'),
                'cover_image_id' => 'co'.fake()->unique()->bothify('????##'),
                'ttb_hastily' => $hours(2, 20, 80),
                'ttb_normally' => $hours(8, 40, 60),
                'ttb_completely' => $hours(30, 120, 40),
                'similar_igdb_ids' => fake()->randomElements(range(1, 400_000), 5),
                'match_status' => MatchStatus::Matched,
                'match_attempts' => 1,
                'enriched_at' => fake()->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    /**
     * IGDB had no match; scheduled for a later retry.
     */
    public function unmatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_status' => MatchStatus::Unmatched,
            'match_attempts' => 3,
            'next_match_at' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
        ]);
    }
}
