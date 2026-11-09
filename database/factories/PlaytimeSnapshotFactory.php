<?php

namespace Database\Factories;

use App\Models\LibraryEntry;
use App\Models\PlaytimeSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaytimeSnapshot>
 */
class PlaytimeSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_entry_id' => LibraryEntry::factory(),
            'captured_on' => fake()->dateTimeBetween('-6 months', '-1 day')->format('Y-m-d'),
            'minutes_total' => fake()->numberBetween(1, 30_000),
        ];
    }
}
