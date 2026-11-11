<?php

namespace Database\Factories;

use App\Models\LibraryEntry;
use App\Models\RecommendationPick;
use App\Models\RecommendationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationPick>
 */
class RecommendationPickFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recommendation_run_id' => RecommendationRun::factory(),
            'library_entry_id' => LibraryEntry::factory(),
            'rank' => fake()->numberBetween(1, 20),
            'score' => fake()->randomFloat(4, 0, 1),
            'contributions' => [
                'taste' => ['value' => 0.5, 'weight' => 0.35, 'weighted' => 0.175],
            ],
            'explanation' => 'Because you like it.',
        ];
    }
}
