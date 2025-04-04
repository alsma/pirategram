<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Models\GameState;

class NullEntityBehavior extends BaseEntityBehavior
{
    public function move(GameState $gameState, Entity $entity, CellPosition $position): void {}
}
