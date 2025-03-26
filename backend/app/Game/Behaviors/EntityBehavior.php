<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Models\GameState;

interface EntityBehavior
{
    public function move(GameState $game, Entity $entity, CellPosition $position): void;
}
