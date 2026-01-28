<?php

declare(strict_types=1);

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Vinkla\Hashids\Facades\Hashids;

trait HashedIdTrait
{
    public static function keyToHashedId(int $key): string
    {
        return Hashids::encode($key);
    }

    public static function hashedIdToKey(string $hashedId): int
    {
        return Hashids::decode($hashedId)[0];
    }

    public function getHashedId(): string
    {
        return Hashids::encode($this->getKey());
    }

    public function scopeOfHashedId(Builder $query, string $hashedId): Builder
    {
        return $query->whereKey(Hashids::decode($hashedId)[0]);
    }
}
