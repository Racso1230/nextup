<?php

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state: a genre.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TagType::Genre,
            'igdb_id' => fake()->unique()->numberBetween(1, 500_000),
            'name' => Str::title(fake()->word().' '.fake()->word()),
        ];
    }

    public function genre(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TagType::Genre]);
    }

    public function theme(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TagType::Theme]);
    }

    public function gameMode(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TagType::GameMode]);
    }

    public function perspective(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TagType::Perspective]);
    }

    public function keyword(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TagType::Keyword]);
    }
}
