<?php

namespace App\Jobs;

use App\Enums\MatchStatus;
use App\Igdb\IgdbApiException;
use App\Igdb\IgdbClient;
use App\Igdb\IgdbRateLimitedException;
use App\Igdb\IgdbStatus;
use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Maps Steam appids to IGDB game ids for pending games and for unmatched games whose retry is due.
 * Unique until processing, so a burst of imports queues one run, but an import that finishes while a run is
 * in progress can still queue one follow-up for its new games. WithoutOverlapping keeps runs one at a time.
 */
class MatchGames implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** Releases the unique lock if a worker dies mid-run. */
    public int $uniqueFor = 3600;

    private const int CHUNK = 500;

    /** Days to wait before retrying, indexed by attempts made so far. Every attempt after the list is 30 days. */
    private const array BACKOFF_DAYS = [1 => 1, 2 => 3, 3 => 7, 4 => 14];

    private const int MAX_BACKOFF_DAYS = 30;

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
            (new WithoutOverlapping('igdb-match'))->releaseAfter(60)->expireAfter(3600),
        ];
    }

    /** Days until the next match attempt, given how many attempts have now been made. */
    public static function backoffDays(int $attempts): int
    {
        return self::BACKOFF_DAYS[$attempts] ?? self::MAX_BACKOFF_DAYS;
    }

    public function handle(IgdbClient $igdb, IgdbStatus $status): void
    {
        if (! $status->configured()) {
            Log::info('MatchGames skipped: IGDB is not configured');

            return;
        }

        $last = 0;

        while (true) {
            /** @var Collection<int, Game> $chunk */
            $chunk = Game::query()
                ->where('id', '>', $last)
                ->where(fn ($q) => $q
                    ->where('match_status', MatchStatus::Pending)
                    ->orWhere(fn ($q) => $q
                        ->where('match_status', MatchStatus::Unmatched)
                        ->where('next_match_at', '<=', now())))
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->get();

            if ($chunk->isEmpty()) {
                break;
            }

            $last = $chunk->last()->id;

            if (! $this->matchChunk($igdb, $chunk)) {
                return;
            }
        }

        // Newly matched games need metadata, and this also picks up stale enrichment.
        EnrichGames::dispatch();
    }

    /**
     * @param  Collection<int, Game>  $chunk
     * @return bool False when the job was released and should stop.
     */
    private function matchChunk(IgdbClient $igdb, Collection $chunk): bool
    {
        $ids = $chunk->modelKeys();
        $previous = $chunk->mapWithKeys(fn (Game $game): array => [$game->id => $game->match_status]);

        // In flight: visible as "retrying" and not picked up by a second run.
        Game::whereIn('id', $ids)->update(['match_status' => MatchStatus::Retrying]);

        try {
            $map = $igdb->steamGameIds($chunk->map(fn (Game $game): int => $game->steam_appid)->values()->all());
        } catch (IgdbRateLimitedException $e) {
            $this->restore($previous);
            $this->release($e->retryAfterSeconds ?? 30);

            return false;
        } catch (IgdbApiException $e) {
            // Put the games back, otherwise the queue's next attempt would skip them as "retrying".
            $this->restore($previous);

            throw $e;
        }

        DB::transaction(function () use ($chunk, $map): void {
            $now = now();

            foreach ($chunk as $game) {
                $attempts = $game->match_attempts + 1;
                $igdbId = $map[$game->steam_appid] ?? null;

                // Per row on purpose: each matched game gets its own igdb_id. Only a background job runs this.
                Game::whereKey($game->id)->update($igdbId === null
                    ? [
                        'match_status' => MatchStatus::Unmatched,
                        'match_attempts' => $attempts,
                        'next_match_at' => $now->addDays(self::backoffDays($attempts)),
                    ]
                    : [
                        'igdb_id' => $igdbId,
                        'match_status' => MatchStatus::Matched,
                        'match_attempts' => $attempts,
                        'next_match_at' => null,
                    ]);
            }
        });

        return true;
    }

    /** @param  SupportCollection<int, MatchStatus>  $previous  game id => status before the run */
    private function restore(SupportCollection $previous): void
    {
        foreach ($previous->groupBy(fn (MatchStatus $s): string => $s->value, preserveKeys: true) as $value => $games) {
            Game::whereIn('id', $games->keys()->all())->update(['match_status' => $value]);
        }
    }

    /**
     * Nothing may stay "retrying" once the job has given up. Retry in an hour.
     */
    public function failed(Throwable $e): void
    {
        Log::warning('Matching games failed', ['exception' => $e::class]);

        Game::where('match_status', MatchStatus::Retrying)->update([
            'match_status' => MatchStatus::Unmatched,
            'next_match_at' => now()->addHour(),
        ]);
    }
}
