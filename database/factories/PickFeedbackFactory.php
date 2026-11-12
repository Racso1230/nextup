<?php

namespace Database\Factories;

use App\Enums\FeedbackKind;
use App\Models\LibraryEntry;
use App\Models\PickFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PickFeedback>
 */
class PickFeedbackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // The entry belongs to the same user, however the user is supplied.
            'library_entry_id' => fn (array $attributes) => LibraryEntry::factory()->create(['user_id' => $attributes['user_id']])->id,
            'kind' => fake()->randomElement(FeedbackKind::cases()),
        ];
    }

    public function ofKind(FeedbackKind $kind): static
    {
        return $this->state(fn () => ['kind' => $kind]);
    }
}
