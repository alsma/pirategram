<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Context\TurnContext;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use Illuminate\Support\Collection;

interface EntityBehavior
{
    public function move(TurnContext $turnContext, Entity $entity, CellPosition $position): void;

    /**
     * @return Collection<int, EntityTurn>
     */
    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection;
}
