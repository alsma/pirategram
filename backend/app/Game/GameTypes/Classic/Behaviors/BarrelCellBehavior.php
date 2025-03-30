<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Behaviors\TurnOverHandlerCellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class BarrelCellBehavior extends BaseCellBehavior implements TurnOverHandlerCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $gameState->entities = $gameState->entities->updateEntity($entity->updateState->set(EntityStateItem::TurnsOnCellLeft->value, 1));
    }

    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection
    {
        if ($entity->state->int(EntityStateItem::TurnsOnCellLeft->value) > 0) {
            $possibleTurns = collect();
        }

        return parent::processPossibleTurns($possibleTurns, $entity, $entities, $context);
    }

    public function onPlayerTurnOver(GameState $gameState, Entity $entity, Cell $cell, CellPosition $position): void
    {
        $turnsLeftOnCell = $entity->state->int(EntityStateItem::TurnsOnCellLeft->value);
        if ($turnsLeftOnCell === 1) {
            $updatedEntity = $entity->updateState->unset(EntityStateItem::TurnsOnCellLeft->value);
            $gameState->entities = $gameState->entities->updateEntity($updatedEntity);
        } elseif ($turnsLeftOnCell > 0) {
            $updatedEntity = $entity->updateState->set(EntityStateItem::TurnsOnCellLeft->value, $turnsLeftOnCell - 1);
            $gameState->entities = $gameState->entities->updateEntity($updatedEntity);
        }
    }
}
