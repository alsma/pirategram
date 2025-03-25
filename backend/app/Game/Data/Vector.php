<?php

declare(strict_types=1);

namespace App\Game\Data;

use Illuminate\Support\Collection;

readonly class Vector
{
    public function __construct(public int $col, public int $row) {}

    public static function createAroundVectors(): Collection
    {
        return collect([
            new self(-1, -1),
            new self(0, -1),
            new self(1, -1),
            new self(-1, 0),
            new self(1, 0),
            new self(-1, 1),
            new self(0, 1),
            new self(1, 1),
        ]);
    }
}
