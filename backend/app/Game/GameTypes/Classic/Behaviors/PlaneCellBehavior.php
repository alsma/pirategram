<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Commands\UpdateCellCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use Illuminate\Support\Collection;

class PlaneCellBehavior extends BaseCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void {}

    public function onLeave(TurnContext $turnContext, Entity $entity, CellPosition $position, Cell $cell, CellPosition $newPosition): void
    {
        parent::onLeave($turnContext, $entity, $position, $cell, $newPosition);

        $newCell = new Cell(CellType::Terrain, true);
        $turnContext->applyCommand(new UpdateCellCommand($entity->position, $newCell, __METHOD__));
    }

    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        $entity = $turnContext->getTurnEntity();

        $possibleTurns = $turnContext
            ->mapCells(function (Cell $cell, CellPosition $position) use ($entity) {
                if ($cell->type === CellType::Water) {
                    return null;
                }

                return new EntityTurn($entity->id, $cell, $position);
            });

        return parent::processPossibleTurns($possibleTurns, $turnContext);
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }
}
