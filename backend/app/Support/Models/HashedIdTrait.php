<?php

declare(strict_types=1);

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Vinkla\Hashids\Facades\Hashids;

trait HashedIdTrait
{
    public function getHashedId(): string
    {
        return Hashids::encode($this->id);
    }

    public function scopeOfHashedId(Builder $query, string $hashedId): Builder
    {
        return $query->whereKey(Hashids::decode($hashedId)[0]);
    }
}
