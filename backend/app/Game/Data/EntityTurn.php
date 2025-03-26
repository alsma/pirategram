<?php

declare(strict_types=1);

namespace App\Game\Data;

readonly class EntityTurn
{
    public function __construct(
        public string $entityId,
        public Cell $cell,
        public CellPosition $position,
    ) {}
}
