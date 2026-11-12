<?php

namespace App\Demo;

use App\Enums\ImportStatus;
use App\Enums\LibraryVisibility;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates a throwaway demo account with the persona's library, entirely from the bundled catalogue.
 * It never calls Steam or IGDB, and uses a fixed number of queries (bulk inserts) whatever the size.
 *
 * Games and tags are upserted on the same keys enrichment uses (steam_appid, and type + igdb_id), so real
 * and demo users share one catalogue and running this twice duplicates nothing.
 *
 * @phpstan-import-type CatalogueGame from DemoLibrary
 */
final class CreateDemoAccount
{
    /**
     * Demo SteamIDs are 17 digits starting with this prefix. Real SteamID64s are 76561197960265728 (account
     * 0) plus a 32-bit account number, so they run from 76561197960265728 to about 76561202255233023. Every
     * number starting 76561190 is below that, so it can never be a real account, yet it is still a
     * 17-digit numeric string and passes the same validation and casts.
     */
    public const string STEAM_ID_PREFIX = '76561190';

    private const int CHUNK = 250;

    public function __invoke(?CarbonImmutable $now = null): User
    {
        $now ??= CarbonImmutable::now('UTC');

        return DB::transaction(function () use ($now): User {
            $user = new User([
                'steam_id' => self::STEAM_ID_PREFIX.str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT),
                'persona_name' => 'Demo player',
                'avatar_url' => null,
                'profile_url' => null,
                'library_visibility' => LibraryVisibility::Public,
                'import_status' => ImportStatus::Done,
                'imported_at' => $now,
            ]);
            // Not mass assignable, so a request can never set it.
            $user->is_demo = true;
            $user->save();

            $catalogue = DemoLibrary::catalogue();

            $gameIds = $this->storeGames($catalogue, $now);
            $this->storeTags($catalogue, $gameIds);
            $this->storeLibrary($user, $catalogue, $gameIds, $now);

            return $user;
        });
    }

    /**
     * @param  list<CatalogueGame>  $catalogue
     * @return array<int, int> steam appid => game id
     */
    private function storeGames(array $catalogue, CarbonImmutable $now): array
    {
        $rows = array_map(fn (array $game): array => [
            'steam_appid' => $game['steam_appid'],
            'name' => $game['name'],
            'icon_hash' => $game['icon_hash'],
            'igdb_id' => $game['igdb_id'],
            'slug' => $game['slug'],
            'first_release_date' => $game['first_release_date'],
            'cover_image_id' => $game['cover_image_id'],
            'ttb_hastily' => $game['ttb_hastily'],
            'ttb_normally' => $game['ttb_normally'],
            'ttb_completely' => $game['ttb_completely'],
            'similar_igdb_ids' => json_encode($game['similar_igdb_ids'] ?? [], JSON_THROW_ON_ERROR),
            'match_status' => 'matched',
            'enriched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $catalogue);

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            // match_attempts and next_match_at keep their defaults, and created_at is not overwritten.
            Game::upsert($chunk, ['steam_appid'], [
                'name', 'icon_hash', 'igdb_id', 'slug', 'first_release_date', 'cover_image_id',
                'ttb_hastily', 'ttb_normally', 'ttb_completely', 'similar_igdb_ids',
                'match_status', 'enriched_at', 'updated_at',
            ]);
        }

        $ids = [];

        foreach (Game::query()->whereIn('steam_appid', array_column($catalogue, 'steam_appid'))->get(['id', 'steam_appid']) as $game) {
            $ids[$game->steam_appid] = $game->id;
        }

        return $ids;
    }

    /**
     * @param  list<CatalogueGame>  $catalogue
     * @param  array<int, int>  $gameIds
     */
    private function storeTags(array $catalogue, array $gameIds): void
    {
        $now = now();
        $tagRows = [];

        foreach ($catalogue as $game) {
            foreach ($game['tags'] as $tag) {
                $tagRows[$tag['type'].':'.$tag['igdb_id']] = [
                    'type' => $tag['type'],
                    'igdb_id' => $tag['igdb_id'],
                    'name' => $tag['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk(array_values($tagRows), self::CHUNK * 2) as $chunk) {
            Tag::upsert($chunk, ['type', 'igdb_id'], ['name', 'updated_at']);
        }

        /** @var array<string, int> $tagIds "type:igdb_id" => tag id */
        $tagIds = [];

        foreach (Tag::query()->whereIn('type', array_unique(array_column($tagRows, 'type')))->get(['id', 'type', 'igdb_id']) as $tag) {
            $tagIds[$tag->type->value.':'.$tag->igdb_id] = $tag->id;
        }

        $pivot = [];

        foreach ($catalogue as $game) {
            foreach ($game['tags'] as $tag) {
                $gameId = $gameIds[$game['steam_appid']];
                $tagId = $tagIds[$tag['type'].':'.$tag['igdb_id']];
                $pivot[$gameId.':'.$tagId] = ['game_id' => $gameId, 'tag_id' => $tagId];
            }
        }

        // Insert-or-ignore: pairs that already exist (from a real user's enrichment) stay as they are.
        foreach (array_chunk(array_values($pivot), 1000) as $chunk) {
            DB::table('game_tag')->insertOrIgnore($chunk);
        }
    }

    /**
     * @param  list<CatalogueGame>  $catalogue
     * @param  array<int, int>  $gameIds
     */
    private function storeLibrary(User $user, array $catalogue, array $gameIds, CarbonImmutable $now): void
    {
        $entries = [];

        foreach ($catalogue as $game) {
            $play = DemoPersona::play($game['steam_appid'], $now);

            $entries[] = [
                'user_id' => $user->id,
                'game_id' => $gameIds[$game['steam_appid']],
                'playtime_minutes' => $play->minutes ?? 0,
                'playtime_2weeks_minutes' => $play?->minutes2Weeks,
                'last_played_at' => $play?->lastPlayedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($entries, self::CHUNK) as $chunk) {
            LibraryEntry::insert($chunk);
        }

        $entryIds = LibraryEntry::query()->where('user_id', $user->id)->pluck('id', 'game_id')->all();

        $snapshots = [];

        foreach ($entries as $entry) {
            $snapshots[] = [
                'library_entry_id' => $entryIds[$entry['game_id']],
                'captured_on' => $now->toDateString(),
                'minutes_total' => $entry['playtime_minutes'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($snapshots, self::CHUNK) as $chunk) {
            DB::table('playtime_snapshots')->insert($chunk);
        }

        $friends = [];

        foreach (DemoPersona::friendActivity() as $appId => $count) {
            $friends[] = [
                'user_id' => $user->id,
                'steam_appid' => $appId,
                'friends_played' => $count,
                'captured_on' => $now->toDateString(),
            ];
        }

        DB::table('friend_activity')->insert($friends);
    }
}
