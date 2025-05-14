<?php

declare(strict_types=1);

namespace App\Game\Casts;

use App\Game\Data\State;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Model;

class StateCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): State
    {
        return new State(Json::decode($value));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (!$value instanceof State) {
            throw new \InvalidArgumentException('$value must be State instance');
        }

        return [$key => Json::encode($value->toArray())];
    }
}
