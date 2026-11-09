<?php

namespace App\Http\Controllers;

use App\Enums\LibraryVisibility;
use App\Http\Requests\LibraryIndexRequest;
use App\Models\LibraryEntry;
use App\Steam\SteamCdn;
use App\Steam\SteamPrivacy;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    private const int PER_PAGE = 50;

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
            ->with('game');

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
            fn (LibraryEntry $entry): array => [
                'id' => $entry->id,
                'name' => $entry->game->name,
                'app_id' => $entry->game->steam_appid,
                'icon_url' => SteamCdn::iconUrl($entry->game->steam_appid, $entry->game->icon_hash),
                'playtime_minutes' => $entry->playtime_minutes,
                'last_played_at' => $entry->last_played_at?->toIso8601String(),
                'status' => $entry->status->value,
            ],
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
