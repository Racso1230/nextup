<?php

namespace App\Models;

use App\Enums\FeedbackKind;
use App\Enums\LibraryStatus;
use Database\Factories\LibraryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $game_id
 * @property int $playtime_minutes
 * @property int|null $playtime_2weeks_minutes
 * @property Carbon|null $last_played_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LibraryStatus $status
 * @property-read Game $game
 * @property-read PlaytimeSnapshot|null $latestSnapshot
 */
#[Fillable(['user_id', 'game_id', 'playtime_minutes', 'playtime_2weeks_minutes', 'last_played_at'])]
class LibraryEntry extends Model
{
    /** @use HasFactory<LibraryEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'playtime_minutes' => 'integer',
            'playtime_2weeks_minutes' => 'integer',
            'last_played_at' => 'datetime',
        ];
    }

    /**
     * Derived from playtime, never stored.
     *
     * @return Attribute<LibraryStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): LibraryStatus => $this->deriveStatus());
    }

    private function deriveStatus(): LibraryStatus
    {
        return match (true) {
            $this->playtime_minutes === 0 => LibraryStatus::Unplayed,
            ($this->playtime_2weeks_minutes ?? 0) > 0 => LibraryStatus::Recent,
            default => LibraryStatus::Played,
        };
    }

    /**
     * The derived status as a SQL expression yielding 'unplayed', 'recent' or 'played'. This is the one
     * place the status rules exist in SQL; keep it in step with deriveStatus(). A constant (not a method)
     * so static analysis can see it is a literal string, never user input.
     */
    public const string STATUS_SQL = "case when library_entries.playtime_minutes = 0 then 'unplayed'"
        ." when coalesce(library_entries.playtime_2weeks_minutes, 0) > 0 then 'recent'"
        ." else 'played' end";

    /** SQL expression ranking statuses for sorting: unplayed 0, recent 1, played 2. */
    public const string STATUS_RANK_SQL = 'case '.self::STATUS_SQL." when 'unplayed' then 0 when 'recent' then 1 else 2 end";

    /**
     * Filter by derived status.
     *
     * @param  Builder<LibraryEntry>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, LibraryStatus $status): void
    {
        $query->whereRaw('('.self::STATUS_SQL.') = ?', [$status->value]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return HasMany<PlaytimeSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(PlaytimeSnapshot::class);
    }

    /**
     * @return HasMany<PickFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(PickFeedback::class);
    }

    /**
     * Entries whose latest feedback is "never suggest".
     *
     * @param  Builder<LibraryEntry>  $query
     */
    #[Scope]
    protected function neverSuggested(Builder $query): void
    {
        $query->whereHas('feedback', fn ($feedback) => $feedback
            ->latestPerEntry()
            ->where('kind', FeedbackKind::Never->value));
    }

    /**
     * @return HasOne<PlaytimeSnapshot, $this>
     */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(PlaytimeSnapshot::class)->latestOfMany('captured_on');
    }
}
