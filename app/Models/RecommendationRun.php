<?php

namespace App\Models;

use Database\Factories\RecommendationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One stored ranking of a user's library: the weights used and the top picks.
 *
 * @property int $id
 * @property int $user_id
 * @property array<string, float> $weights Signal name to the weight used.
 * @property int $candidate_count
 * @property array{hours: float, mood: string|null, company: string|null}|null $context The session context it was ranked with.
 * @property Carbon|null $created_at
 * @property-read User $user
 * @property-read Collection<int, RecommendationPick> $picks
 */
#[Fillable(['user_id', 'weights', 'candidate_count', 'context'])]
class RecommendationRun extends Model
{
    /** @use HasFactory<RecommendationRunFactory> */
    use HasFactory;

    /** Runs are immutable, so there is no updated_at column. */
    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weights' => 'array',
            'candidate_count' => 'integer',
            'context' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<RecommendationPick, $this>
     */
    public function picks(): HasMany
    {
        return $this->hasMany(RecommendationPick::class)->orderBy('rank');
    }
}
