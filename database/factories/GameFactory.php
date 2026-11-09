<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
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
}
