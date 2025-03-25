<?php

declare(strict_types=1);

namespace App\Game\Data;

readonly class CellPosition
{
    public function __construct(
        public int $col,
        public int $row,
    ) {}
}
