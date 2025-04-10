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
use App\Game\Data\Vector;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class KnightCellBehavior extends BaseCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void {}

    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection
    {
        /** @var GameBoard $gameBoard */
        $gameBoard = $context->mustGet('gameBoard');

        $baseVectors = collect([
            new Vector(2, 1),
            new Vector(2, -1),
            new Vector(-2, 1),
            new Vector(-2, -1),
            new Vector(1, 2),
            new Vector(1, -2),
            new Vector(-1, 2),
            new Vector(-1, -2),
        ]);

        $possibleTurns = $baseVectors->map(function (Vector $vector) use ($entity, $gameBoard) {
            $position = $entity->position->add($vector);
            $cell = $gameBoard->getCell($position);
            if (!$cell) {
                return null;
            }

            if ($cell->type === CellType::Water) {
                return null;
            }

            return new EntityTurn($entity->id, $cell, $position);
        })->filter();

        return parent::processPossibleTurns($possibleTurns, $entity, $entities, $context);
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }
}
