<?php

namespace App\Recommendations;

use App\Enums\MatchStatus;
use App\Models\FriendActivity;
use App\Models\User;
use App\Recommender\Feedback;
use App\Recommender\GameFacts;
use App\Recommender\RecommendationContext;
use App\Recommender\SessionContext;
use App\Recommender\TagRef;
use App\Recommender\Weights;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Loads one user's library and maps it to the plain DTOs the recommender works on. This is the only place
 * the recommender meets the database: a fixed number of queries however large the library is.
 */
final class LibraryContextFactory
{
    /** The momentum window, matching the "last 14 days" in docs/recommender.md. */
    private const int WINDOW_DAYS = 14;

    public function build(User $user, CarbonImmutable $now, ?SessionContext $session = null): RecommendationContext
    {
        $entries = $user->libraryEntries()->with('game.tags')->get();
        $recent = $this->recentMinutes($user, $entries->isNotEmpty(), $now);

        $friends = $this->friendCounts($user);

        $library = [];

        foreach ($entries as $entry) {
            $game = $entry->game;
            $tags = [];

            foreach ($game->tags as $tag) {
                $tags[] = new TagRef($tag->type, $tag->igdb_id, $tag->name);
            }

            $library[] = new GameFacts(
                entryId: $entry->id,
                igdbId: $game->match_status === MatchStatus::Matched ? $game->igdb_id : null,
                name: $game->name,
                playtimeMinutes: $entry->playtime_minutes,
                playtime2WeeksMinutes: $entry->playtime_2weeks_minutes,
                recentMinutesFromSnapshots: $recent === null || ! array_key_exists($entry->id, $recent)
                    ? null
                    : max(0, $entry->playtime_minutes - $recent[$entry->id]),
                lastPlayedAt: $entry->last_played_at?->toDateTimeImmutable(),
                ttbNormallySeconds: $game->ttb_normally,
                tags: $tags,
                friendsPlayedRecently: $friends === null ? null : ($friends[$game->steam_appid] ?? 0),
            );
        }

        return new RecommendationContext(
            library: $library,
            now: $now,
            weights: Weights::fromArray(config()->array('recommender.weights')),
            session: $session ?? SessionContext::default(),
            feedback: $this->latestFeedback($user),
        );
    }

    /**
     * Friends who played each game recently, keyed by steam appid. Null when the user has no friend activity
     * rows at all (friends list private, or not fetched yet), so the social signal stays null. When there are
     * rows, a game without one was played by no friend, which is a real 0.
     *
     * @return array<int, int>|null
     */
    private function friendCounts(User $user): ?array
    {
        $counts = [];

        foreach (FriendActivity::where('user_id', $user->id)->get(['steam_appid', 'friends_played']) as $row) {
            $counts[$row->steam_appid] = $row->friends_played;
        }

        return $counts === [] ? null : $counts;
    }

    /**
     * The user's latest feedback per entry, in one query.
     *
     * @return array<int, Feedback>
     */
    private function latestFeedback(User $user): array
    {
        $feedback = [];

        // Ordered by id so that, should two rows tie on time, the later one wins.
        foreach ($user->feedback()->latestPerEntry()->orderBy('id')->get() as $row) {
            $feedback[$row->library_entry_id] = new Feedback(
                $row->kind,
                ($row->created_at ?? now())->toDateTimeImmutable(),
            );
        }

        return $feedback;
    }

    /**
     * Each entry's lifetime minutes as of 14 days ago, keyed by entry id, or null when the user's snapshots
     * don't reach back that far (the window isn't covered, so Steam's playtime_2weeks is used instead).
     * Entries first seen inside the window have no key: they have no baseline.
     *
     * @return array<int, int>|null
     */
    private function recentMinutes(User $user, bool $hasEntries, CarbonImmutable $now): ?array
    {
        if (! $hasEntries) {
            return null;
        }

        // A subselect rather than a list of ids, so a big library never hits the bound-parameter limit.
        $entryIds = fn ($query) => $query->select('id')->from('library_entries')->where('user_id', $user->id);

        $cutoff = $now->utc()->subDays(self::WINDOW_DAYS)->toDateString();

        $earliest = DB::table('playtime_snapshots')->whereIn('library_entry_id', $entryIds)->min('captured_on');

        if (! is_string($earliest) || substr($earliest, 0, 10) > $cutoff) {
            return null;
        }

        // The latest snapshot on or before the cutoff, per entry: a grouped subquery joined back, which
        // works the same on sqlite and Postgres. A missing day means "unchanged", hence "latest on or before".
        $latest = DB::table('playtime_snapshots')
            ->select('library_entry_id', DB::raw('max(captured_on) as captured_on'))
            ->whereIn('library_entry_id', $entryIds)
            ->whereDate('captured_on', '<=', $cutoff)
            ->groupBy('library_entry_id');

        $rows = DB::table('playtime_snapshots as s')
            ->joinSub($latest, 'l', fn ($join) => $join
                ->on('l.library_entry_id', '=', 's.library_entry_id')
                ->on('l.captured_on', '=', 's.captured_on'))
            ->get(['s.library_entry_id', 's.minutes_total']);

        $byEntry = [];

        foreach ($rows as $row) {
            $byEntry[(int) $row->library_entry_id] = (int) $row->minutes_total;
        }

        return $byEntry;
    }
}
