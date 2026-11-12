<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackKind;
use App\Enums\LibraryVisibility;
use App\Enums\TagType;
use App\Http\Requests\LibraryIndexRequest;
use App\Igdb\IgdbImages;
use App\Models\LibraryEntry;
use App\Models\PickFeedback;
use App\Models\Tag;
use App\Steam\SteamCdn;
use App\Steam\SteamPrivacy;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    private const int PER_PAGE = 50;

    private const int MAX_TAGS = 2;

    public function index(LibraryIndexRequest $request): Response
    {
        $user = $request->user() ?? abort(401);

        $sort = $request->sortColumn();
        $direction = $request->sortDirection();
        $status = $request->statusFilter();
        $hiddenOnly = $request->hiddenOnly();
        $search = $request->searchTerm();

        $query = LibraryEntry::query()
            ->join('games', 'games.id', '=', 'library_entries.game_id')
            ->where('library_entries.user_id', $user->id)
            ->select('library_entries.*')
            // Only genres and themes are shown, so load nothing else (keywords average about 10 per game).
            ->with(['game.tags' => fn ($tags) => $tags->whereIn('tags.type', [TagType::Genre->value, TagType::Theme->value])]);

        if ($status !== null) {
            $query->withStatus($status);
        }

        if ($hiddenOnly) {
            $query->neverSuggested();
        }

        if ($search !== null) {
            $this->whereNameContains($query, $search);
        }

        match ($sort) {
            'name' => $query->orderByRaw("lower(games.name) {$direction}"),
            'playtime' => $query->orderBy('library_entries.playtime_minutes', $direction),
            'status' => $query->orderByRaw(LibraryEntry::STATUS_RANK_SQL.' '.$direction),
            // Never-played games go last whichever way the dates run (portable: no NULLS LAST).
            default => $query->orderByRaw('library_entries.last_played_at is null')
                ->orderBy('library_entries.last_played_at', $direction),
        };

        // Stable tiebreak so pages never shuffle between requests.
        $query->orderByRaw('lower(games.name) asc')->orderBy('library_entries.id');

        $page = $query->paginate(self::PER_PAGE)->withQueryString();
        $hidden = $this->hiddenIds($user->id, $page->getCollection()->pluck('id')->values()->all());
        $entries = $page->through(
            fn (LibraryEntry $entry): array => $this->row($entry, in_array($entry->id, $hidden, true)),
        );

        $isPrivate = $user->library_visibility === LibraryVisibility::Private;

        return Inertia::render('Library', [
            'entries' => $entries,
            'filters' => [
                'sort' => $sort,
                'direction' => $direction,
                'status' => $hiddenOnly ? LibraryIndexRequest::HIDDEN : $status?->value,
                'q' => $search,
            ],
            'status_counts' => $this->statusCounts($user->id, $search),
            'hidden_count' => $this->hiddenCount($user->id, $search),
            'total' => LibraryEntry::query()->where('user_id', $user->id)->count(),
            'import' => [
                'status' => $user->import_status->value,
                'visibility' => $user->library_visibility->value,
                'imported_at' => $user->imported_at?->toIso8601String(),
            ],
            'privacy' => $isPrivate
                ? ['settings_url' => SteamPrivacy::SETTINGS_URL, 'steps' => SteamPrivacy::steps()]
                : null,
        ]);
    }

    /**
     * Which of these entries are never-suggested (their latest feedback is "never"): one query for the page.
     *
     * @param  array<int, mixed>  $entryIds
     * @return array<int, int>
     */
    private function hiddenIds(int $userId, array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        return PickFeedback::query()
            ->where('user_id', $userId)
            ->whereIn('library_entry_id', $entryIds)
            ->latestPerEntry()
            ->where('kind', FeedbackKind::Never->value)
            ->pluck('library_entry_id')
            ->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->values()
            ->all();
    }

    /** Never-suggested games for the current search, ignoring the status filter. */
    private function hiddenCount(int $userId, ?string $search): int
    {
        $query = LibraryEntry::query()
            ->join('games', 'games.id', '=', 'library_entries.game_id')
            ->where('library_entries.user_id', $userId)
            ->neverSuggested();

        if ($search !== null) {
            $this->whereNameContains($query, $search);
        }

        return $query->count();
    }

    /**
     * Entries per status for the current search, ignoring the status filter, so each segment's count
     * matches what choosing it would show. One grouped query.
     *
     * @return array{all: int, unplayed: int, recent: int, played: int}
     */
    private function statusCounts(int $userId, ?string $search): array
    {
        $query = LibraryEntry::query()
            ->join('games', 'games.id', '=', 'library_entries.game_id')
            ->where('library_entries.user_id', $userId);

        if ($search !== null) {
            $this->whereNameContains($query, $search);
        }

        $counts = $query
            ->selectRaw(LibraryEntry::STATUS_SQL.' as status, count(*) as aggregate')
            ->groupByRaw(LibraryEntry::STATUS_SQL)
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => is_numeric($count) ? (int) $count : 0);

        $unplayed = $counts->get('unplayed', 0);
        $recent = $counts->get('recent', 0);
        $played = $counts->get('played', 0);

        return ['all' => $unplayed + $recent + $played, 'unplayed' => $unplayed, 'recent' => $recent, 'played' => $played];
    }

    /**
     * One table row. Deliberately omits igdb_id and the other internals the UI does not use.
     *
     * @return array<string, mixed>
     */
    private function row(LibraryEntry $entry, bool $hidden): array
    {
        $game = $entry->game;

        // Only genres and themes are shown (the rest stay in the database for the recommender).
        // Genres come first, so themes only fill the slots genres leave free.
        $order = [TagType::Genre, TagType::Theme];
        $display = $game->tags
            ->filter(fn (Tag $tag): bool => in_array($tag->type, $order, true))
            ->sort(fn (Tag $a, Tag $b): int => [array_search($a->type, $order, true), mb_strtolower($a->name)]
                <=> [array_search($b->type, $order, true), mb_strtolower($b->name)])
            ->values();

        return [
            'id' => $entry->id,
            'name' => $game->name,
            'app_id' => $game->steam_appid,
            'icon_url' => SteamCdn::iconUrl($game->steam_appid, $game->icon_hash),
            'cover_url' => IgdbImages::coverUrl($game->cover_image_id, 'cover_small'),
            'tags' => $display->take(self::MAX_TAGS)
                ->map(fn (Tag $tag): array => ['name' => $tag->name, 'type' => $tag->type->value])
                ->all(),
            'match_status' => $game->match_status->value,
            'playtime_minutes' => $entry->playtime_minutes,
            'last_played_at' => $entry->last_played_at?->toIso8601String(),
            'status' => $entry->status->value,
            'hidden' => $hidden,
        ];
    }

    /**
     * Case-insensitive "contains" on the game name. Compares lower() on both sides, which behaves the
     * same on sqlite and pgsql, and escapes LIKE wildcards so "%" and "_" in the input match literally.
     *
     * @param  Builder<LibraryEntry>  $query
     */
    private function whereNameContains(Builder $query, string $search): void
    {
        // "!" is the escape character, so the pattern has no backslashes to get wrong across drivers.
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));

        $query->whereRaw("lower(games.name) like ? escape '!'", ["%{$escaped}%"]);
    }
}
