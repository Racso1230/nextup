<?php

namespace App\Jobs;

use App\Models\FriendActivity;
use App\Models\User;
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

/**
 * Rebuilds a user's friend activity: for each of up to 50 friends, which games they played in the last
 * two weeks, reduced to "how many friends played game X". Friends' Steam ids live only in memory for the
 * length of this job. They are never stored and never logged, only counts are.
 */
class RefreshFriendActivity implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One Steam call per friend, spaced by the shared 'steam' limiter (60 a minute). Fifty friends is about
     * a minute of the budget for a nightly job, which is polite; more would crowd out library imports.
     * Which friends are picked doesn't matter, the signal is "a few friends played this".
     */
    public const int MAX_FRIENDS = 50;

    public int $tries = 4;

    public bool $deleteWhenMissingModels = true;

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

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RateLimited('steam')];
    }

    public function handle(SteamClient $steam): void
    {
        $user = $this->user;

        // Demo accounts have invented friend counts and never talk to Steam.
        if ($user->is_demo) {
            return;
        }

        try {
            $friendIds = $steam->friendIds($user->steam_id);

            if ($friendIds === null) {
                // Private list: store nothing, and drop anything stored while it was public.
                FriendActivity::where('user_id', $user->id)->delete();

                return;
            }

            /** @var array<int, int> $counts appid => friends who played it */
            $counts = [];

            foreach (array_slice($friendIds, 0, self::MAX_FRIENDS) as $friendId) {
                $appIds = $steam->recentlyPlayedAppIds($friendId);

                // A private friend says nothing either way.
                foreach ($appIds ?? [] as $appId) {
                    $counts[$appId] = ($counts[$appId] ?? 0) + 1;
                }
            }
        } catch (SteamRateLimitedException $e) {
            $this->release($e->retryAfterSeconds ?? 60);

            return;
        }

        $today = CarbonImmutable::now('UTC')->toDateString();

        $rows = [];

        foreach ($counts as $appId => $count) {
            $rows[] = [
                'user_id' => $user->id,
                'steam_appid' => $appId,
                'friends_played' => $count,
                'captured_on' => $today,
            ];
        }

        DB::transaction(function () use ($user, $rows): void {
            FriendActivity::where('user_id', $user->id)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                FriendActivity::insert($chunk);
            }
        });
    }

    public function failed(Throwable $e): void
    {
        $context = ['user_id' => $this->user->id, 'exception' => $e::class];

        // Steam exceptions hold the endpoint and status only, never a URL or a friend id.
        if ($e instanceof SteamApiException) {
            $context += ['endpoint' => $e->endpoint, 'status' => $e->status];
        }

        Log::warning('Friend activity refresh failed', $context);
    }
}
