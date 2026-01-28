<?php

declare(strict_types=1);

namespace App\MatchMaking\Models;

use App\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $leader_id
 * @property string $mode
 * @property string $status
 * @property User $leader
 * @property Collection<PartyMember> $members
 */
class Party extends Model
{
    protected $fillable = ['leader_id', 'mode', 'status'];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }
}
