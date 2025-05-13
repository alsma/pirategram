<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use Illuminate\Support\Collection;

class NullCellBehavior extends BaseCellBehavior
{
    public function onLeave(TurnContext $turnContext, Entity $entity, CellPosition $position, Cell $cell, CellPosition $newPosition): void {}

    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void {}

    public function allowsEntityToStay(): bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        return $possibleTurns;
    }
}
