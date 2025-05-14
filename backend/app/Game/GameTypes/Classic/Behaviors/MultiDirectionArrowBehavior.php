<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Exceptions\RuntimeException;
use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Behaviors\RotatableCellBehaviorTrait;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\Vector;
use Illuminate\Support\Collection;

class MultiDirectionArrowBehavior extends BaseCellBehavior
{
    use RotatableCellBehaviorTrait;

    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void {}

    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        $possibleTurns = parent::processPossibleTurns($possibleTurns, $turnContext);

        $contextData = $turnContext->getData();
        $entity = $turnContext->getTurnEntity();

        /** @var Cell $currentCell */
        $currentCell = $contextData->mustGet('currentCell');
        /** @var Collection<string, CellPosition> $allowedPositions */
        $allowedPositions = $this->getTurnVectors($currentCell)
            ->map(fn (Vector $vector) => $entity->position->add($vector))
            ->keyBy->__toString();

        return $possibleTurns->filter(fn (EntityTurn $turn) => $allowedPositions->has((string) $turn->position));
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }

    private function getTurnVectors(Cell $cell): Collection
    {
        $baseVectors = match ($cell->type) {
            CellType::Arrow2 => collect([new Vector(-1, 0), new Vector(1, 0)]),
            CellType::Arrow2Diagonal => collect([new Vector(1, -1), new Vector(-1, 1)]),
            CellType::Arrow3 => collect([new Vector(-1, -1), new Vector(1, 0), new Vector(0, 1)]),
            CellType::Arrow4 => collect([new Vector(0, -1), new Vector(1, 0), new Vector(0, 1), new Vector(-1, 0)]),
            CellType::Arrow4Diagonal => collect([new Vector(-1, -1), new Vector(1, -1), new Vector(1, 1), new Vector(-1, 1)]),
            default => throw new RuntimeException("Unexpected cell type '{$cell->type->value}'.")
        };

        return $this->rotateVectors($cell->direction ?? 0, $baseVectors);
    }
}
