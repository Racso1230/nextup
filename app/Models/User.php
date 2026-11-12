<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Enums\LibraryVisibility;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $steam_id SteamID64, always handled as a string (too large for a JS number).
 * @property string $persona_name
 * @property string|null $avatar_url
 * @property string|null $profile_url
 * @property LibraryVisibility $library_visibility
 * @property ImportStatus $import_status
 * @property Carbon|null $imported_at
 * @property Carbon|null $feedback_changed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['steam_id', 'persona_name', 'avatar_url', 'profile_url', 'library_visibility', 'import_status', 'imported_at'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values, mirroring the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'library_visibility' => 'unknown',
        'import_status' => 'idle',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'steam_id' => 'string',
            'library_visibility' => LibraryVisibility::class,
            'import_status' => ImportStatus::class,
            'imported_at' => 'datetime',
            'feedback_changed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<LibraryEntry, $this>
     */
    public function libraryEntries(): HasMany
    {
        return $this->hasMany(LibraryEntry::class);
    }

    /**
     * @return HasMany<RecommendationRun, $this>
     */
    public function recommendationRuns(): HasMany
    {
        return $this->hasMany(RecommendationRun::class);
    }

    /**
     * @return HasMany<PickFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(PickFeedback::class);
    }

    /**
     * @return HasMany<FriendActivity, $this>
     */
    public function friendActivity(): HasMany
    {
        return $this->hasMany(FriendActivity::class);
    }
}
