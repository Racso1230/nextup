<?php

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Enums\LibraryVisibility;
use App\Http\Requests\PlayNextRequest;
use App\Igdb\IgdbImages;
use App\Models\LibraryEntry;
use App\Models\RecommendationPick;
use App\Models\User;
use App\Recommendations\LatestRecommendations;
use App\Steam\SteamCdn;
use App\Steam\SteamPrivacy;
use Inertia\Inertia;
use Inertia\Response;

class PlayNextController extends Controller
{
    private const int MAX_PICKS = 10;

    /** The signals shown as bars on the top pick, in order, with their labels. The mismatch and snooze penalties are left out. */
    private const array SIGNAL_LABELS = [
        'taste' => 'Taste match',
        'momentum' => 'Momentum',
        'fit' => 'Fits tonight',
        // Null (so left out) when no mood is chosen. The snooze penalty is never shown, like mismatch.
        'mood' => 'Mood match',
        // Covers both "never started" (1.0) and "tried once, never went back" (0.5).
        'freshness' => 'Fresh start',
    ];

    /** Freshness is a nudge, not a measurement: a 0 bar would read as a claim, so it is only shown when it applies. */
    private const array HIDE_WHEN_ZERO = ['freshness'];

    public function index(PlayNextRequest $request, LatestRecommendations $latest): Response
    {
        $user = $request->user() ?? abort(401);

        $run = $latest($user, $request->sessionContext());
        $picks = $run === null ? collect() : $run->picks->take(self::MAX_PICKS)->load('libraryEntry.game');

        $isPrivate = $user->library_visibility === LibraryVisibility::Private;

        return Inertia::render('PlayNext', [
            'picks' => $picks->map(fn (RecommendationPick $pick): array => $this->pick($pick))->values()->all(),
            'filters' => $request->filters(),
            'ranked_at' => $run?->created_at?->toIso8601String(),
            'state' => $picks->isNotEmpty() ? 'ready' : $this->emptyState($user, $isPrivate),
            'privacy' => $isPrivate
                ? ['settings_url' => SteamPrivacy::SETTINGS_URL, 'steps' => SteamPrivacy::steps()]
                : null,
            'import' => [
                'status' => $user->import_status->value,
                'visibility' => $user->library_visibility->value,
                'imported_at' => $user->imported_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Why there is nothing to show: still importing, a private library, no games, or no candidates.
     */
    private function emptyState(User $user, bool $isPrivate): string
    {
        if (in_array($user->import_status, [ImportStatus::Queued, ImportStatus::Running], true)) {
            return 'importing';
        }

        if ($isPrivate) {
            return 'private';
        }

        return LibraryEntry::query()->where('user_id', $user->id)->exists() ? 'nothing' : 'empty';
    }

    /**
     * @return array<string, mixed>
     */
    private function pick(RecommendationPick $pick): array
    {
        $game = $pick->libraryEntry->game;
        $top = $pick->rank === 1;

        $row = [
            'rank' => $pick->rank,
            'entry_id' => $pick->library_entry_id,
            'name' => $game->name,
            'app_id' => $game->steam_appid,
            'icon_url' => SteamCdn::iconUrl($game->steam_appid, $game->icon_hash),
            'cover_url' => IgdbImages::coverUrl($game->cover_image_id, $top ? 'cover_big' : 'cover_small'),
            'explanation' => $pick->explanation,
            'score' => max(0, min(100, (int) round($pick->score * 100))),
            'playtime_minutes' => $pick->libraryEntry->playtime_minutes,
        ];

        if ($top) {
            $row['signals'] = $this->signals($pick);
        }

        return $row;
    }

    /**
     * @return list<array{key: string, label: string, value: float}>
     */
    private function signals(RecommendationPick $pick): array
    {
        $signals = [];

        foreach (self::SIGNAL_LABELS as $key => $label) {
            $value = $pick->contributions[$key]['value'] ?? null;

            if ($value === null) {
                continue;
            }

            $value = max(0.0, min(1.0, (float) $value));

            if ($value === 0.0 && in_array($key, self::HIDE_WHEN_ZERO, true)) {
                continue;
            }

            $signals[] = ['key' => $key, 'label' => $label, 'value' => $value];
        }

        return $signals;
    }
}
