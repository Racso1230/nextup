<?php

namespace App\Models;

use App\Enums\MatchStatus;
use App\Igdb\IgdbImages;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $steam_appid
 * @property string $name Untrusted text from Steam, escaped on output.
 * @property string|null $icon_hash
 * @property int|null $igdb_id
 * @property string|null $slug
 * @property Carbon|null $first_release_date
 * @property string|null $cover_image_id
 * @property int|null $ttb_hastily Seconds. Null means unknown, never zero.
 * @property int|null $ttb_normally Seconds. Null means unknown, never zero.
 * @property int|null $ttb_completely Seconds. Null means unknown, never zero.
 * @property list<int>|null $similar_igdb_ids
 * @property MatchStatus $match_status
 * @property int $match_attempts
 * @property Carbon|null $next_match_at
 * @property Carbon|null $enriched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'steam_appid', 'name', 'icon_hash',
    'igdb_id', 'slug', 'first_release_date', 'cover_image_id',
    'ttb_hastily', 'ttb_normally', 'ttb_completely',
    'similar_igdb_ids', 'match_status', 'match_attempts', 'next_match_at', 'enriched_at',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * Default attribute values, mirroring the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'match_status' => 'pending',
        'match_attempts' => 0,
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'steam_appid' => 'integer',
            'igdb_id' => 'integer',
            'first_release_date' => 'date',
            'ttb_hastily' => 'integer',
            'ttb_normally' => 'integer',
            'ttb_completely' => 'integer',
            'similar_igdb_ids' => 'array',
            'match_status' => MatchStatus::class,
            'match_attempts' => 'integer',
            'next_match_at' => 'datetime',
            'enriched_at' => 'datetime',
        ];
    }

    /**
     * Only matched games are recommendation candidates.
     *
     * @param  Builder<Game>  $query
     */
    #[Scope]
    protected function matched(Builder $query): void
    {
        $query->where('games.match_status', MatchStatus::Matched);
    }

    /**
     * The IGDB cover, or null when the game has none.
     */
    public function coverUrl(string $size = 'cover_big'): ?string
    {
        return IgdbImages::coverUrl($this->cover_image_id, $size);
    }

    /**
     * @return HasMany<LibraryEntry, $this>
     */
    public function libraryEntries(): HasMany
    {
        return $this->hasMany(LibraryEntry::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
