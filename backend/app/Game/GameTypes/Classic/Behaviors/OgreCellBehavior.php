<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Models\GameState;

class OgreCellBehavior extends BaseCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $updatedPirate = $entity->updateState($entity->state->set(EntityStateItem::IsKilled->value, true));
        $gameState->entities = $gameState->entities->updateEntity($updatedPirate);
    }
}
