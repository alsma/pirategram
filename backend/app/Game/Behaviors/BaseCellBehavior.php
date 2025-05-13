<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use Illuminate\Support\Collection;

abstract class BaseCellBehavior implements CellBehavior
{
    public function onLeave(TurnContext $turnContext, Entity $entity, CellPosition $position, Cell $cell, CellPosition $newPosition): void {}

    public function allowsEntityToStay(): bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        return $possibleTurns;
    }

    public function allowsEntityToBeCarriedTo(Entity $carrier, Entity $carriage, Cell $cell, CellPosition $cellPosition, TurnContext $turnContext): bool
    {
        if ($carriage->type === EntityType::Coin) {
            return $cell->revealed;
        }

        return true;
    }
}
