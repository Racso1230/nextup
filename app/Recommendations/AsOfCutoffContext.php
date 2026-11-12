<?php

namespace App\Recommendations;

use App\Enums\MatchStatus;
use App\Models\User;
use App\Recommender\GameFacts;
use App\Recommender\RecommendationContext;
use App\Recommender\TagRef;
use App\Recommender\Weights;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The library as it was on a past date, for the time-split backtest (docs/eval/method.md, section 2).
 * Rebuilt from playtime snapshots; tags and time to beat are today's values (IGDB metadata barely changes).
 * Snapshots are written when playtime changes, so a missing day means "unchanged".
 */
final class AsOfCutoffContext
{
    /** How far before the latest snapshot the cut-off sits. */
    public const int SPLIT_DAYS = 90;

    /** The momentum window, as in LibraryContextFactory. */
    private const int WINDOW_DAYS = 14;

    /**
     * Null when the user's history starts after the cut-off. `relevant` lists the entries played after it.
     *
     * @return array{context: RecommendationContext, relevant: list<int>}|null
     */
    public function build(User $user, CarbonImmutable $cutoff): ?array
    {
        $cutoffDate = $cutoff->utc()->toDateString();
        $earliest = $this->firstSnapshotOn($user);

        if ($earliest === null || $earliest->toDateString() > $cutoffDate) {
            return null;
        }

        $windowStart = $cutoff->utc()->subDays(self::WINDOW_DAYS)->toDateString();
        $windowCovered = $earliest->toDateString() <= $windowStart;

        $states = $this->entryStates($user, $cutoffDate, $windowStart);

        $library = [];
        $relevant = [];

        foreach ($user->libraryEntries()->with('game.tags')->get() as $entry) {
            $state = $states[$entry->id] ?? null;

            // First seen after the cut-off (or never snapshotted): not in the library yet.
            if ($state === null) {
                continue;
            }

            $recent = $windowCovered && $state['baseline'] !== null ? max(0, $state['minutes'] - $state['baseline']) : null;
            $game = $entry->game;
            $tags = [];

            foreach ($game->tags as $tag) {
                $tags[] = new TagRef($tag->type, $tag->igdb_id, $tag->name);
            }

            $library[] = new GameFacts(
                entryId: $entry->id,
                igdbId: $game->match_status === MatchStatus::Matched ? $game->igdb_id : null,
                name: $game->name,
                playtimeMinutes: $state['minutes'],
                playtime2WeeksMinutes: $recent,
                recentMinutesFromSnapshots: $recent,
                lastPlayedAt: $state['last_growth'] === null ? null : new CarbonImmutable($state['last_growth'], 'UTC'),
                ttbNormallySeconds: $game->ttb_normally,
                tags: $tags,
            );

            if ($entry->playtime_minutes > $state['minutes']) {
                $relevant[] = $entry->id;
            }
        }

        return [
            'context' => new RecommendationContext($library, $cutoff, Weights::fromArray(config()->array('recommender.weights'))),
            'relevant' => $relevant,
        ];
    }

    /** The cut-off for a 90-day split: the user's latest snapshot date minus 90 days. Null without snapshots. */
    public function cutoffFor(User $user): ?CarbonImmutable
    {
        return $this->latestSnapshotOn($user)?->subDays(self::SPLIT_DAYS);
    }

    /** More days of snapshots needed before a 90-day split exists; 0 when it does. */
    public function daysUntilAvailable(User $user): int
    {
        $earliest = $this->firstSnapshotOn($user);
        $latest = $this->latestSnapshotOn($user);

        if ($earliest === null || $latest === null) {
            return self::SPLIT_DAYS;
        }

        return max(0, self::SPLIT_DAYS - (int) $earliest->diffInDays($latest));
    }

    /** The date of the user's first snapshot (when collection started), or null if there are none. */
    public function firstSnapshotOn(User $user): ?CarbonImmutable
    {
        return $this->snapshotDate($user, 'min');
    }

    private function latestSnapshotOn(User $user): ?CarbonImmutable
    {
        return $this->snapshotDate($user, 'max');
    }

    private function snapshotDate(User $user, string $aggregate): ?CarbonImmutable
    {
        $value = $this->snapshots($user)->{$aggregate}('captured_on');

        return is_string($value) ? CarbonImmutable::parse(substr($value, 0, 10), 'UTC')->startOfDay() : null;
    }

    private function snapshots(User $user): Builder
    {
        // A subselect rather than a list of ids, so a big library never hits the bound-parameter limit.
        return DB::table('playtime_snapshots')->whereIn(
            'library_entry_id',
            fn ($query) => $query->select('id')->from('library_entries')->where('user_id', $user->id),
        );
    }

    /**
     * Per entry first seen on or before the cut-off, from one ordered pass over its snapshots:
     * minutes as of the cut-off, the date of the last growth, and the minutes at the start of the 14-day window.
     *
     * @return array<int, array{minutes: int, last_growth: ?string, baseline: ?int}>
     */
    private function entryStates(User $user, string $cutoffDate, string $windowStart): array
    {
        $states = [];

        $rows = $this->snapshots($user)
            ->whereDate('captured_on', '<=', $cutoffDate)
            ->orderBy('library_entry_id')
            ->orderBy('captured_on')
            ->cursor();

        foreach ($rows as $row) {
            $id = (int) $row->library_entry_id;
            $date = substr((string) $row->captured_on, 0, 10);
            $minutes = (int) $row->minutes_total;
            $state = $states[$id] ?? ['minutes' => $minutes, 'last_growth' => null, 'baseline' => null];

            // The first snapshot is a starting total, not growth; later ones that grew mark a play day.
            if (isset($states[$id]) && $minutes > $state['minutes']) {
                $state['last_growth'] = $date;
            }

            if ($date <= $windowStart) {
                $state['baseline'] = $minutes;
            }

            $state['minutes'] = $minutes;
            $states[$id] = $state;
        }

        return $states;
    }
}
