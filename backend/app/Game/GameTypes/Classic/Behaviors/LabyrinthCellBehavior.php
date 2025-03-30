<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Exceptions\RuntimeException;
use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityTurn;
use App\Game\Data\GameBoard;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class LabyrinthCellBehavior extends BaseCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
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
        $updatedState = ($turnsOnCellLeft === 0)
            ? $entity->state->unset(EntityStateItem::TurnsOnCellLeft->value)
            : $entity->state->set(EntityStateItem::TurnsOnCellLeft->value, $turnsOnCellLeft);

        $updatedEntity = $entity->updateState($updatedState);
        $gameState->entities = $gameState->entities->updateEntity($updatedEntity);
    }

    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection
    {
        if ($entity->state->int(EntityStateItem::TurnsOnCellLeft->value) > 0) {
            /** @var GameBoard $gameBoard */
            $gameBoard = $context->mustGet('gameBoard');

            $possibleTurns = collect([
                new EntityTurn($entity->id, $gameBoard->getCell($entity->position), $entity->position),
            ]);
        }

        return parent::processPossibleTurns($possibleTurns, $entity, $entities, $context);
    }
}
