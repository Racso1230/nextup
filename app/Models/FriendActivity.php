<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How many of a user's friends played one game in the last two weeks. Only the count is stored, never
 * who the friends are. Replaced wholesale on each refresh.
 *
 * @property int $id
 * @property int $user_id
 * @property int $steam_appid
 * @property int $friends_played
 * @property Carbon $captured_on
 * @property-read User $user
 */
#[Fillable(['user_id', 'steam_appid', 'friends_played', 'captured_on'])]
class FriendActivity extends Model
{
    public $timestamps = false;

    protected $table = 'friend_activity';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
