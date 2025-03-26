<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

interface CellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void;

    public function allowsEntityToStay(): bool;

    /**
     * @param  Collection<int, EntityTurn>  $possibleTurns
     * @param  Collection<int, Entity>  $entities
     * @return Collection<int, EntityTurn>
     */
    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection;
}
