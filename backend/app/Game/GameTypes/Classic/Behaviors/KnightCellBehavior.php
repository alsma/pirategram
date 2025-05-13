<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\Vector;
use Illuminate\Support\Collection;

class KnightCellBehavior extends BaseCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void {}

    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
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

        $entity = $turnContext->getTurnEntity();
        $possibleTurns = $baseVectors->map(function (Vector $vector) use ($turnContext, $entity) {
            $position = $entity->position->add($vector);
            $cell = $turnContext->getCell($position);
            if (!$cell) {
                return null;
            }

            if ($cell->type === CellType::Water) {
                return null;
            }

            return new EntityTurn($entity->id, $cell, $position);
        })->filter();

        return parent::processPossibleTurns($possibleTurns, $turnContext);
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }
}
