<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Models\GameState;

class IceCellBehavior extends BaseCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $vector = $entity->position->difference($prevPosition);
        $newPosition = $entity->position->add($vector);

        $updatedPirate = $entity->updatePosition($newPosition);
        $gameState->entities = $gameState->entities->updateEntity($updatedPirate);
    }
}
