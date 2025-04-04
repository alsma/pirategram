<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Models\GameState;

class NullCellBehavior extends BaseCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void {}
}
