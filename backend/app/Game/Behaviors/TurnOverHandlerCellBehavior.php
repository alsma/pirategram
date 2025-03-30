<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Models\GameState;

interface TurnOverHandlerCellBehavior
{
    /**
     * This method will be executed for all player entities except the one player made turn with
     */
    public function onPlayerTurnOver(GameState $gameState, Entity $entity, Cell $cell, CellPosition $position): void;
}
