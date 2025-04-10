<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

abstract class BaseCellBehavior implements CellBehavior
{
    public function onLeave(GameState $gameState, Entity $entity, CellPosition $position, Cell $cell, CellPosition $newPosition): void
    {
    }

    public function allowsEntityToStay(): bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection
    {
        return $possibleTurns;
    }
}
