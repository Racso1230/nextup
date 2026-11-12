<?php

namespace Database\Factories;

use App\Models\RecommendationRun;
use App\Models\User;
use App\Recommender\SessionContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationRun>
 */
class RecommendationRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'weights' => config('recommender.weights'),
            'candidate_count' => fake()->numberBetween(1, 200),
            // Ranked for the default session, so a stored run is reusable for a plain page view.
            'context' => SessionContext::default()->toArray(),
        ];
    }
}
