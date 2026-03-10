<?php

declare(strict_types=1);

namespace App\Social\Models;

use App\Support\Models\HashedIdTrait;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $friend_id
 * @property string $status
 * @property User $user
 * @property User $friend
 */
class Friendship extends Model
{
    use HashedIdTrait;

    protected $fillable = ['user_id', 'friend_id', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }
}
