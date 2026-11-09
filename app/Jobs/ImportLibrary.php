<?php

namespace App\Jobs;

use App\Actions\RecordPlaytimeSnapshots;
use App\Enums\ImportStatus;
use App\Enums\LibraryVisibility;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\User;
use App\Steam\OwnedGame;
use App\Steam\SteamApiException;
use App\Steam\SteamClient;
use App\Steam\SteamRateLimitedException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportLibrary implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /**
     * If the user deletes their account while an import is queued, drop the job instead of failing it.
     */
    public bool $deleteWhenMissingModels = true;

    private const int CHUNK = 500;

    public function __construct(public User $user) {}

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    /**
     * The 'steam' limiter is global (Steam's cap is per API key). RateLimited uses the default cache
     * store, which is Redis outside tests, so this is the Redis-backed limiter CLAUDE.md asks for.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('steam')];
    }

    public function handle(SteamClient $steam, RecordPlaytimeSnapshots $snapshots): void
    {
        $user = $this->user;
        $user->update(['import_status' => ImportStatus::Running]);

        try {
            $summary = $steam->playerSummary($user->steam_id);

            if ($summary !== null) {
                $user->fill([
                    'persona_name' => $summary->personaName,
                    'avatar_url' => $summary->avatarFullUrl,
                    'profile_url' => $summary->profileUrl,
                ]);
            }

            $result = $steam->ownedGames($user->steam_id);
        } catch (SteamRateLimitedException $e) {
            // Back in the queue, so the library page shows "queued" rather than a stuck "running".
            $user->update(['import_status' => ImportStatus::Queued]);
            $this->release($e->retryAfterSeconds ?? 60);

            return;
        }

        if ($result->isPrivate) {
            // Keep existing entries: a private library is not an empty one.
            $user->fill([
                'library_visibility' => LibraryVisibility::Private,
                'import_status' => ImportStatus::Private,
            ])->save();

            return;
        }

        DB::transaction(function () use ($user, $result, $snapshots): void {
            $this->storeGames($user, $result->games);
            $snapshots($user, CarbonImmutable::now('UTC')->startOfDay());

            $user->fill([
                'library_visibility' => LibraryVisibility::Public,
                'imported_at' => now(),
                'import_status' => ImportStatus::Done,
            ])->save();
        });
    }

    public function failed(Throwable $e): void
    {
        $context = ['user_id' => $this->user->id, 'exception' => $e::class];

        // Steam exceptions are built from the endpoint and status only. Other messages may hold a URL.
        if ($e instanceof SteamApiException) {
            $context += ['endpoint' => $e->endpoint, 'status' => $e->status];
        }

        Log::warning('Library import failed', $context);

        $this->user->update(['import_status' => ImportStatus::Failed]);
    }

    /** @param  list<OwnedGame>  $games */
    private function storeGames(User $user, array $games): void
    {
        $now = now();

        foreach (array_chunk($games, self::CHUNK) as $chunk) {
            Game::upsert(
                array_map(fn (OwnedGame $g): array => [
                    'steam_appid' => $g->appId,
                    'name' => $g->name,
                    'icon_hash' => $g->iconHash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk),
                ['steam_appid'],
                ['name', 'icon_hash', 'updated_at'],
            );
        }

        /** @var array<int, int> $ids appid => game id */
        $ids = [];

        foreach (array_chunk(array_map(fn (OwnedGame $g): int => $g->appId, $games), self::CHUNK) as $appIds) {
            $ids += Game::whereIn('steam_appid', $appIds)->pluck('id', 'steam_appid')->all();
        }

        // Entries for games that left the owned list are kept. Phase 2 snapshots hang off entries.
        foreach (array_chunk($games, self::CHUNK) as $chunk) {
            LibraryEntry::upsert(
                array_map(fn (OwnedGame $g): array => [
                    'user_id' => $user->id,
                    'game_id' => $ids[$g->appId],
                    'playtime_minutes' => $g->playtimeMinutes,
                    'playtime_2weeks_minutes' => $g->playtime2WeeksMinutes,
                    'last_played_at' => $g->lastPlayedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk),
                ['user_id', 'game_id'],
                ['playtime_minutes', 'playtime_2weeks_minutes', 'last_played_at', 'updated_at'],
            );
        }
    }
}
