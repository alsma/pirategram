<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\CellPosition;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

interface EntityBehavior
{
    public function move(GameState $game, Entity $entity, CellPosition $position): void;

    /**
     * @param  Collection<int, EntityTurn>  $possibleTurns
     * @param  Collection<int, Entity>  $entities
     * @return Collection<int, EntityTurn>
     */
    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection;
}
