<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Game\Casts\EntitiesCollectionCast;
use App\Game\Casts\GameBoardCast;
use App\Game\Data\GameType;
use App\Support\Models\HashedIdTrait;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class GameState extends Model
{
    use HasFactory, HashedIdTrait;

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, GamePlayer::class, secondKey: 'id', secondLocalKey: 'user_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function currentTurn(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'current_turn_game_player_id');
    }

    public function finalizeTurn(): self
    {
        $nextTurn = $this->players
            ->sortBy('order', SORT_NUMERIC)
            ->firstWhere('order', '>', $this->currentTurn->order);
        if (!$nextTurn) {
            $nextTurn = $this->players->firstWhere('order', 0);
        }

        $this->currentTurn()->associate($nextTurn);
        $this->save();

        return $this;
    }

    protected function casts(): array
    {
        return [
            'type' => GameType::class,
            'board' => GameBoardCast::class,
            'entities' => EntitiesCollectionCast::class,
        ];
    }
}
