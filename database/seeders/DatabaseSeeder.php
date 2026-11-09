<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a demo user with a small library. No external calls are made.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561197960287930',
            'persona_name' => 'DemoPlayer',
        ]);

        foreach (Game::factory()->count(20)->create() as $index => $game) {
            $entry = LibraryEntry::factory()->state([
                'user_id' => $user->id,
                'game_id' => $game->id,
            ]);

            // 8 unplayed, 4 recent, 8 played.
            $entry = match (true) {
                $index < 8 => $entry->unplayed(),
                $index < 12 => $entry->recent(),
                default => $entry,
            };

            $entry->create();
        }
    }
}
