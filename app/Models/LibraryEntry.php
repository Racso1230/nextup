<?php

namespace App\Models;

use App\Enums\LibraryStatus;
use Database\Factories\LibraryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Filter by derived status. Mirrors deriveStatus() in SQL; keep the two in step.
     *
     * @param  Builder<LibraryEntry>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, LibraryStatus $status): void
    {
        match ($status) {
            LibraryStatus::Unplayed => $query->where('library_entries.playtime_minutes', 0),
            LibraryStatus::Recent => $query->where('library_entries.playtime_minutes', '<>', 0)
                ->whereRaw('coalesce(library_entries.playtime_2weeks_minutes, 0) > 0'),
            LibraryStatus::Played => $query->where('library_entries.playtime_minutes', '<>', 0)
                ->whereRaw('coalesce(library_entries.playtime_2weeks_minutes, 0) <= 0'),
        };
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
}
