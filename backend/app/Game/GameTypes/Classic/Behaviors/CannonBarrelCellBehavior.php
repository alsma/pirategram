<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Behaviors\RotatableCellBehaviorTrait;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\Vector;

class CannonBarrelCellBehavior extends BaseCellBehavior
{
    use RotatableCellBehaviorTrait;

    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $baseVector = new Vector(0, -1);
        $vector = $this->rotateVector($cell->direction, $baseVector);

        $newPosition = $position;

        do {
            $newPosition = $newPosition->add($vector);
            $newCell = $turnContext->getCell($newPosition);
        } while ($newCell->type !== CellType::Water);

        $turnContext->applyCommand(new UpdateEntityPositionCommand($entity->id, $newPosition, __METHOD__));
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }
}
