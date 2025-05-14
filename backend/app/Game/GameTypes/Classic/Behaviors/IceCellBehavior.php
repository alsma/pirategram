<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Commands\KillEntityCommand;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;

class IceCellBehavior extends BaseCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $vector = $entity->position->difference($prevPosition);
        $newPosition = $entity->position->add($vector);

        if ($turnContext->hasCell($newPosition)) {
            $turnContext->applyCommand(new UpdateEntityPositionCommand($entity->id, $newPosition, __METHOD__));
        } else {
            // handle knight x2 behavior and plane behavior
            $turnContext->applyCommand(new KillEntityCommand($entity->id, __METHOD__));
        }
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }

    public function allowsEntityToBeCarriedTo(Entity $carrier, Entity $carriage, Cell $cell, CellPosition $cellPosition, TurnContext $turnContext): bool
    {
        if (!parent::allowsEntityToBeCarriedTo($carrier, $carriage, $cell, $cellPosition, $turnContext)) {
            return false;
        }

        $vector = $cellPosition->difference($carrier->position);
        $newPosition = $cellPosition->add($vector);

        $newCell = $turnContext->getCell($newPosition);
        if ($newCell) {
            // TODO probably bug when cell type has allowsEntityToBeCarriedTo method
            return parent::allowsEntityToBeCarriedTo($carrier, $carriage, $newCell, $newPosition, $turnContext);
        }

        return false;
    }
}
