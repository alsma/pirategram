<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Behaviors\TurnOverHandlerCellBehavior;
use App\Game\Commands\UpdateEntityStateCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use Illuminate\Support\Collection;

class BarrelCellBehavior extends BaseCellBehavior implements TurnOverHandlerCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $turnContext->applyCommand(UpdateEntityStateCommand::set($entity->id, EntityStateItem::TurnsOnCellLeft->value, 1, __METHOD__));
    }

    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        if ($turnContext->getTurnEntity()->state->int(EntityStateItem::TurnsOnCellLeft->value) > 0) {
            $possibleTurns = collect();
        }

        return parent::processPossibleTurns($possibleTurns, $turnContext);
    }

    public function onPlayerTurnOver(TurnContext $turnContext, Entity $entity, Cell $cell, CellPosition $position): void
    {
        $turnsLeftOnCell = $entity->state->int(EntityStateItem::TurnsOnCellLeft->value);
        if ($turnsLeftOnCell === 1) {
            $turnContext->applyCommand(UpdateEntityStateCommand::unset($entity->id, EntityStateItem::TurnsOnCellLeft->value, __METHOD__));
        } elseif ($turnsLeftOnCell > 0) {
            $turnContext->applyCommand(UpdateEntityStateCommand::set($entity->id, EntityStateItem::TurnsOnCellLeft->value, $turnsLeftOnCell - 1, __METHOD__));
        }
    }
}
