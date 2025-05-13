<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Exceptions\RuntimeException;
use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Commands\UpdateEntityStateCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityTurn;
use Illuminate\Support\Collection;

class LabyrinthCellBehavior extends BaseCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $turnsOnCellLeft = $entity->state->int(EntityStateItem::TurnsOnCellLeft->value, -1);

        if ($turnsOnCellLeft === -1) {
            // First time entering the labyrinth, set the appropriate turns
            $turnsOnCellLeft = match ($cell->type) {
                CellType::Labyrinth2 => 1,
                CellType::Labyrinth3 => 2,
                CellType::Labyrinth4 => 3,
                CellType::Labyrinth5 => 4,
                default => throw new RuntimeException("Unexpected cell type '{$cell->type->value}'.")
            };
        } elseif ($turnsOnCellLeft > 0) {
            // Decrement turns left
            $turnsOnCellLeft--;
        }

        // If turns left is now zero, remove the state
        $updateStateCommand = ($turnsOnCellLeft === 0)
            ? UpdateEntityStateCommand::unset($entity->id, EntityStateItem::TurnsOnCellLeft->value, __METHOD__)
            : UpdateEntityStateCommand::set($entity->id, EntityStateItem::TurnsOnCellLeft->value, $turnsOnCellLeft, __METHOD__);

        $turnContext->applyCommand($updateStateCommand);
    }

    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        $entity = $turnContext->getTurnEntity();

        if ($entity->state->int(EntityStateItem::TurnsOnCellLeft->value) > 0) {
            $possibleTurns = collect([
                new EntityTurn($entity->id, $turnContext->getCell($entity->position), $entity->position),
            ]);
        }

        return parent::processPossibleTurns($possibleTurns, $turnContext);
    }
}
