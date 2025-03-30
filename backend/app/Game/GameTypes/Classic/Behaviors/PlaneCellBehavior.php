<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\GameBoard;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class PlaneCellBehavior extends BaseCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void {}

    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection
    {
        /** @var GameBoard $gameBoard */
        $gameBoard = $context->mustGet('gameBoard');

        $possibleTurns = $gameBoard
            ->mapCells(function (Cell $cell, CellPosition $position) use ($entity) {
                if ($cell->type === CellType::Water) {
                    return null;
                }

                return new EntityTurn($entity->id, $cell, $position);
            });

        return parent::processPossibleTurns($possibleTurns, $entity, $entities, $context);
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }

    public function singleTimeUsage(): bool
    {
        return true;
    }
}
