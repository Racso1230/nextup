<?php

namespace App\Http\Controllers;

use App\Enums\LibraryVisibility;
use App\Enums\TagType;
use App\Http\Requests\LibraryIndexRequest;
use App\Igdb\IgdbImages;
use App\Models\LibraryEntry;
use App\Models\Tag;
use App\Steam\SteamCdn;
use App\Steam\SteamPrivacy;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    private const int PER_PAGE = 50;

    private const int MAX_TAGS = 4;

    public function index(LibraryIndexRequest $request): Response
    {
        $user = $request->user() ?? abort(401);

        $sort = $request->sortColumn();
        $direction = $request->sortDirection();
        $status = $request->statusFilter();
        $search = $request->searchTerm();

        $query = LibraryEntry::query()
            ->join('games', 'games.id', '=', 'library_entries.game_id')
            ->where('library_entries.user_id', $user->id)
            ->select('library_entries.*')
            // Keywords are never displayed, so don't load them (a real library averages about 10 per game).
            ->with(['game.tags' => fn ($tags) => $tags->where('tags.type', '!=', TagType::Keyword->value)]);

        if ($status !== null) {
            $query->withStatus($status);
        }

        if ($search !== null) {
            $this->whereNameContains($query, $search);
        }

        match ($sort) {
            'name' => $query->orderByRaw("lower(games.name) {$direction}"),
            'playtime' => $query->orderBy('library_entries.playtime_minutes', $direction),
            // Never-played games go last whichever way the dates run (portable: no NULLS LAST).
            default => $query->orderByRaw('library_entries.last_played_at is null')
                ->orderBy('library_entries.last_played_at', $direction),
        };

        // Stable tiebreak so pages never shuffle between requests.
        $query->orderByRaw('lower(games.name) asc')->orderBy('library_entries.id');

        $entries = $query->paginate(self::PER_PAGE)->withQueryString()->through(
            fn (LibraryEntry $entry): array => $this->row($entry),
        );

        $isPrivate = $user->library_visibility === LibraryVisibility::Private;

        return Inertia::render('Library', [
            'entries' => $entries,
            'filters' => [
                'sort' => $sort,
                'direction' => $direction,
                'status' => $status?->value,
                'q' => $search,
            ],
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
     * One table row. Deliberately omits igdb_id and the other internals the UI does not use.
     *
     * @return array<string, mixed>
     */
    private function row(LibraryEntry $entry): array
    {
        $game = $entry->game;

        // Keywords stay in the database for the recommender, but there are far too many and they are
        // too noisy to show, so they never reach the page. Display order is the order of this list.
        $order = [TagType::Genre, TagType::Theme, TagType::GameMode, TagType::Perspective];
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
            'tag_overflow' => max(0, $display->count() - self::MAX_TAGS),
            'match_status' => $game->match_status->value,
            'playtime_minutes' => $entry->playtime_minutes,
            'last_played_at' => $entry->last_played_at?->toIso8601String(),
            'status' => $entry->status->value,
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
