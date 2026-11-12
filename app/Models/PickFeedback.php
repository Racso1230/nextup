<?php

namespace App\Models;

use App\Enums\FeedbackKind;
use Database\Factories\PickFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing a user said about a recommended game. Every click is a row; the latest per entry is what counts.
 *
 * @property int $id
 * @property int $user_id
 * @property int $library_entry_id
 * @property FeedbackKind $kind
 * @property Carbon|null $created_at
 * @property-read User $user
 * @property-read LibraryEntry $libraryEntry
 */
#[Fillable(['user_id', 'library_entry_id', 'kind', 'created_at'])]
class PickFeedback extends Model
{
    /** @use HasFactory<PickFeedbackFactory> */
    use HasFactory;

    /** Rows are immutable, so there is no updated_at column. */
    const UPDATED_AT = null;

    protected $table = 'pick_feedback';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackKind::class,
        ];
    }

    /**
     * Keep only the newest row of each entry: those with no newer row for the same entry (ties go to the
     * higher id). A correlated NOT EXISTS, so it behaves the same on sqlite and Postgres.
     *
     * @param  Builder<PickFeedback>  $query
     */
    #[Scope]
    protected function latestPerEntry(Builder $query): void
    {
        $query->whereNotExists(fn ($newer) => $newer
            ->selectRaw('1')
            ->from('pick_feedback as newer')
            ->whereColumn('newer.library_entry_id', 'pick_feedback.library_entry_id')
            ->where(fn ($later) => $later
                ->whereColumn('newer.created_at', '>', 'pick_feedback.created_at')
                ->orWhere(fn ($tie) => $tie
                    ->whereColumn('newer.created_at', 'pick_feedback.created_at')
                    ->whereColumn('newer.id', '>', 'pick_feedback.id'))));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LibraryEntry, $this>
     */
    public function libraryEntry(): BelongsTo
    {
        return $this->belongsTo(LibraryEntry::class);
    }
}
