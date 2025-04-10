<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Behaviors\RotatableCellBehaviorTrait;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\Vector;
use App\Game\Models\GameState;

class CannonBarrelCellBehavior extends BaseCellBehavior
{
    use RotatableCellBehaviorTrait;

    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $baseVector = new Vector(0, -1);
        $vector = $this->rotateVector($cell->direction, $baseVector);

        $newPosition = $position;

        do {
            $newPosition = $newPosition->add($vector);
            $newCell = $gameState->board->getCell($newPosition);
        } while ($newCell->type !== CellType::Water);

        $updatedPirate = $entity->updatePosition($newPosition);
        $gameState->entities = $gameState->entities->updateEntity($updatedPirate);
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }
}
