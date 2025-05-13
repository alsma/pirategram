<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;

class CrocodileCellBehavior extends BaseCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $turnContext->applyCommand(new UpdateEntityPositionCommand($entity->id, $prevPosition, __METHOD__));
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }
}
