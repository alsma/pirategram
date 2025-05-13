<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Exceptions\RuntimeException;
use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Behaviors\RotatableCellBehaviorTrait;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\Vector;

class SingleArrowCellBehavior extends BaseCellBehavior
{
    use RotatableCellBehaviorTrait;

    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $baseVector = match ($cell->type) {
            CellType::Arrow1 => new Vector(0, -1),
            CellType::Arrow1Diagonal => new Vector(1, -1),
            default => throw new RuntimeException("Unexpected cell type '{$cell->type->value}'.")
        };

        $vector = $this->rotateVector($cell->direction, $baseVector);
        $newPosition = $entity->position->add($vector);
        $turnContext->applyCommand(new UpdateEntityPositionCommand($entity->id, $newPosition, __METHOD__));
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }
}
