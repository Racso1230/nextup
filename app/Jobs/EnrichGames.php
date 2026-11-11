<?php

namespace App\Jobs;

use App\Enums\MatchStatus;
use App\Igdb\IgdbApiException;
use App\Igdb\IgdbClient;
use App\Igdb\IgdbGame;
use App\Igdb\IgdbRateLimitedException;
use App\Igdb\IgdbStatus;
use App\Igdb\TimeToBeat;
use App\Models\Game;
use App\Models\Tag;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches IGDB metadata (release date, cover, similar games, time to beat, tags) for matched games that have
 * none yet or whose data is over 30 days old. Unique until processing and never overlapping, like MatchGames.
 */
class EnrichGames implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** Releases the unique lock if a worker dies mid-run. */
    public int $uniqueFor = 3600;

    private const int CHUNK = 500;

    private const int MAX_NAME_LENGTH = 255;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            new RateLimited('igdb'),
            (new WithoutOverlapping('igdb-enrich'))->releaseAfter(60)->expireAfter(3600),
        ];
    }

    public function handle(IgdbClient $igdb, IgdbStatus $status): void
    {
        if (! $status->configured()) {
            Log::info('EnrichGames skipped: IGDB is not configured');

            return;
        }

        $last = 0;

        while (true) {
            // Distinct, because several Steam games can share one IGDB game.
            $igdbIds = Game::query()
                ->where('match_status', MatchStatus::Matched)
                ->whereNotNull('igdb_id')
                ->where('igdb_id', '>', $last)
                ->where(fn ($q) => $q->whereNull('enriched_at')->orWhere('enriched_at', '<', now()->subDays(30)))
                ->distinct()
                ->orderBy('igdb_id')
                ->limit(self::CHUNK)
                ->get(['igdb_id'])
                ->map(fn (Game $game): int => $game->igdb_id ?? 0)
                ->values()
                ->all();

            if ($igdbIds === []) {
                return;
            }

            $last = $igdbIds[array_key_last($igdbIds)];

            try {
                $games = [];
                foreach ($igdb->games($igdbIds) as $game) {
                    $games[$game->id] = $game;
                }
                $times = $igdb->timesToBeat($igdbIds);
            } catch (IgdbRateLimitedException $e) {
                $this->release($e->retryAfterSeconds ?? 30);

                return;
            }

            $this->store($igdbIds, $games, $times);
        }
    }

    /**
     * Writes one chunk in a single transaction, in a fixed number of queries however many games it holds:
     * one upsert for tags, one upsert for all game rows, one delete and one insert for the pivot.
     *
     * @param  array<int, int>  $igdbIds
     * @param  array<int, IgdbGame>  $games
     * @param  array<int, TimeToBeat>  $times
     */
    private function store(array $igdbIds, array $games, array $times): void
    {
        DB::transaction(function () use ($igdbIds, $games, $times): void {
            $now = now();
            $rows = Game::whereIn('igdb_id', $igdbIds)->get(['id', 'steam_appid', 'name', 'igdb_id']);

            // IGDB no longer returns these ids (merged or removed). Clearing the id sends the games back
            // through matching, which finds the replacement id, or schedules a retry.
            $vanished = array_values(array_diff($igdbIds, array_keys($games)));
            if ($vanished !== []) {
                Game::whereIn('igdb_id', $vanished)->update([
                    'igdb_id' => null,
                    'match_status' => MatchStatus::Unmatched,
                    'next_match_at' => $now->addDay(),
                ]);
            }

            $tagRows = [];
            foreach ($games as $game) {
                foreach ($game->tags as $tag) {
                    $name = self::cleanName($tag->name);
                    if ($name !== null) {
                        $tagRows[$tag->type->value.':'.$tag->igdbId] = [
                            'type' => $tag->type->value,
                            'igdb_id' => $tag->igdbId,
                            'name' => $name,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            foreach (array_chunk(array_values($tagRows), self::CHUNK) as $part) {
                Tag::upsert($part, ['type', 'igdb_id'], ['name', 'updated_at']);
            }

            /** @var array<string, int> $tagIds "type:igdb_id" => tag id */
            $tagIds = [];
            if ($tagRows !== []) {
                // Tags are a small vocabulary, so reading it back by type is cheaper than one OR per tag.
                $types = array_unique(array_column($tagRows, 'type'));
                foreach (Tag::whereIn('type', $types)->get(['id', 'type', 'igdb_id']) as $tag) {
                    $tagIds[$tag->type->value.':'.$tag->igdb_id] = $tag->id;
                }
            }

            $gameRows = [];
            $pivot = [];
            foreach ($rows as $row) {
                $igdbGame = $games[$row->igdb_id ?? 0] ?? null;
                if ($igdbGame === null) {
                    continue;
                }

                $ttb = $times[$igdbGame->id] ?? null;
                $gameRows[] = [
                    'id' => $row->id,
                    // Required by NOT NULL on the insert half of the upsert. Not in the update list.
                    'steam_appid' => $row->steam_appid,
                    'name' => $row->name,
                    'slug' => $igdbGame->slug === null ? null : mb_substr(trim($igdbGame->slug), 0, self::MAX_NAME_LENGTH),
                    'first_release_date' => $igdbGame->firstReleaseDate?->toDateString(),
                    'cover_image_id' => $igdbGame->coverImageId,
                    'similar_igdb_ids' => json_encode($igdbGame->similarIgdbIds, JSON_THROW_ON_ERROR),
                    'ttb_hastily' => $ttb?->hastily,
                    'ttb_normally' => $ttb?->normally,
                    'ttb_completely' => $ttb?->completely,
                    'enriched_at' => $now,
                    'updated_at' => $now,
                ];

                foreach ($igdbGame->tags as $tag) {
                    $tagId = $tagIds[$tag->type->value.':'.$tag->igdbId] ?? null;
                    if ($tagId !== null) {
                        $pivot[$row->id.':'.$tagId] = ['game_id' => $row->id, 'tag_id' => $tagId];
                    }
                }
            }

            foreach (array_chunk($gameRows, 250) as $part) {
                // Upsert on the primary key: one statement per chunk, and `name` is left alone because
                // the Steam name is the one the user recognises.
                Game::upsert($part, ['id'], [
                    'slug', 'first_release_date', 'cover_image_id', 'similar_igdb_ids',
                    'ttb_hastily', 'ttb_normally', 'ttb_completely', 'enriched_at', 'updated_at',
                ]);
            }

            // Sync each game's tags to exactly the IGDB set: wipe the chunk's pivot rows, then insert them.
            foreach (array_chunk(array_column($gameRows, 'id'), self::CHUNK) as $ids) {
                DB::table('game_tag')->whereIn('game_id', $ids)->delete();
            }
            foreach (array_chunk(array_values($pivot), 1000) as $part) {
                DB::table('game_tag')->insert($part);
            }
        });
    }

    /** IGDB text is untrusted: plain string, trimmed, capped. Vue escapes it on output. */
    private static function cleanName(string $name): ?string
    {
        $name = mb_substr(trim($name), 0, self::MAX_NAME_LENGTH);

        return $name === '' ? null : $name;
    }

    public function failed(Throwable $e): void
    {
        // The message is built from the endpoint and status only, so it is safe to log.
        $context = ['exception' => $e::class];
        if ($e instanceof IgdbApiException) {
            $context += ['endpoint' => $e->endpoint, 'status' => $e->status];
        }

        Log::warning('Enriching games failed', $context);
    }
}
