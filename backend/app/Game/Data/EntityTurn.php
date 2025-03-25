<?php

declare(strict_types=1);

namespace App\Game\Data;

readonly class EntityTurn
{
    public function __construct(
        public string $entityId,
        public CellPosition $cellPosition,
        public bool $allowedWithCoins = false, // TODO think how prevent entity type leak here
    ) {}
}
