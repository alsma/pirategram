<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\CellPosition;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class NullEntityBehavior extends BaseEntityBehavior
{
    public function move(GameState $game, Entity $entity, CellPosition $position): void {}

    /** {@inheritDoc} */
    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection
    {
        return $possibleTurns;
    }
}
