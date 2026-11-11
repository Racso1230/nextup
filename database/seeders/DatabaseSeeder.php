<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\PlaytimeSnapshot;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

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

        $tags = collect([
            Tag::factory()->genre()->count(4),
            Tag::factory()->theme()->count(3),
            Tag::factory()->gameMode()->count(2),
            Tag::factory()->perspective()->count(2),
            Tag::factory()->keyword()->count(4),
        ])->flatMap(fn ($factory) => $factory->create());

        for ($index = 0; $index < 20; $index++) {
            // 12 matched (with tags), 4 unmatched, 4 left pending.
            $game = match (true) {
                $index < 12 => Game::factory()->matched()->create(),
                $index < 16 => Game::factory()->unmatched()->create(),
                default => Game::factory()->create(),
            };

            if ($index < 12) {
                $game->tags()->attach($tags->random(fake()->numberBetween(3, 6))->pluck('id')->all());
            }

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

            $entry = $entry->create();

            $this->seedSnapshots($entry);
        }
    }

    /**
     * Show growth over a few weeks for entries that have playtime. Unplayed games get none.
     */
    private function seedSnapshots(LibraryEntry $entry): void
    {
        if ($entry->playtime_minutes === 0) {
            return;
        }

        $today = Carbon::today();

        foreach ([28 => 0.6, 14 => 0.8, 0 => 1.0] as $daysAgo => $share) {
            PlaytimeSnapshot::factory()->create([
                'library_entry_id' => $entry->id,
                'captured_on' => $today->copy()->subDays($daysAgo)->toDateString(),
                'minutes_total' => (int) round($entry->playtime_minutes * $share),
            ]);
        }
    }
}
