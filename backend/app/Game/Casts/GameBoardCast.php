<?php

declare(strict_types=1);

namespace App\Game\Casts;

use App\Game\Data\GameBoard;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Model;

class GameBoardCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): GameBoard
    {
        return GameBoard::fromArray(Json::decode($value));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (!$value instanceof GameBoard) {
            throw new \InvalidArgumentException('$value must be GameBoard instance');
        }

        return [$key => Json::encode($value->toArray())];
    }
}
