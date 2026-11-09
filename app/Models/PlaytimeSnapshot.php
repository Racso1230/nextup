<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PlaytimeSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lifetime playtime of one library entry as seen by the import on `captured_on` (UTC).
 *
 * @property int $id
 * @property int $library_entry_id
 * @property CarbonImmutable $captured_on
 * @property int $minutes_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LibraryEntry $libraryEntry
 */
#[Fillable(['library_entry_id', 'captured_on', 'minutes_total'])]
class PlaytimeSnapshot extends Model
{
    /** @use HasFactory<PlaytimeSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_on' => 'immutable_date',
            'minutes_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LibraryEntry, $this>
     */
    public function libraryEntry(): BelongsTo
    {
        return $this->belongsTo(LibraryEntry::class);
    }
}
