<?php

namespace App\Models;

use Database\Factories\RecommendationPickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ranked game within a run, with the per-signal numbers and the sentence that explains it.
 *
 * @property int $id
 * @property int $recommendation_run_id
 * @property int $library_entry_id
 * @property int $rank 1 is the top pick.
 * @property float $score
 * @property array<string, array{value: float|null, weight: float, weighted: float}> $contributions
 * @property string $explanation
 * @property-read RecommendationRun $run
 * @property-read LibraryEntry $libraryEntry
 */
#[Fillable(['recommendation_run_id', 'library_entry_id', 'rank', 'score', 'contributions', 'explanation'])]
class RecommendationPick extends Model
{
    /** @use HasFactory<RecommendationPickFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'score' => 'float',
            'contributions' => 'array',
        ];
    }

    /**
     * @return BelongsTo<RecommendationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(RecommendationRun::class, 'recommendation_run_id');
    }

    /**
     * @return BelongsTo<LibraryEntry, $this>
     */
    public function libraryEntry(): BelongsTo
    {
        return $this->belongsTo(LibraryEntry::class);
    }
}
