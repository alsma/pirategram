<?php

declare(strict_types=1);

namespace App\Game\Casts;

use App\Game\Data\Entity;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EntitiesCollectionCast implements CastsAttributes
{
    public function get(Model $model, $key, $value, $attributes): ?Collection
    {
        if (!isset($attributes[$key])) {
            return null;
        }

        $data = Json::decode($attributes[$key]);

        return is_array($data) ? collect($data)->map(Entity::fromArray(...)) : null;
    }

    public function set(Model $model, $key, $value, $attributes): array
    {
        $value = $value instanceof Collection ? $value->toArray() : $value;

        return [$key => Json::encode($value)];
    }
}
