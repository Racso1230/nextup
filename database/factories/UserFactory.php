<?php

namespace Database\Factories;

use App\Enums\LibraryVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // SteamID64: 7656119 followed by 10 digits, always 17 digits long.
        $steamId = '7656119'.fake()->unique()->numerify('##########');
        $avatarHash = fake()->sha1();

        return [
            'steam_id' => $steamId,
            'persona_name' => fake()->userName(),
            'avatar_url' => "https://avatars.steamstatic.com/{$avatarHash}_full.jpg",
            'profile_url' => "https://steamcommunity.com/profiles/{$steamId}/",
            'library_visibility' => LibraryVisibility::Public,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user's Steam library is private.
     */
    public function privateLibrary(): static
    {
        return $this->state(fn (array $attributes) => [
            'library_visibility' => LibraryVisibility::Private,
        ]);
    }
}
