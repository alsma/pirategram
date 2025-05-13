<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use Illuminate\Support\Collection;

interface CellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void;

    public function onLeave(TurnContext $turnContext, Entity $entity, CellPosition $position, Cell $cell, CellPosition $newPosition): void;

    public function allowsEntityToStay(): bool;

    /**
     * This method should filter possible entity turns or return new set of possible turns according to rules
     * It should not produce any side effects (such as entity modification etc.)
     *
     * @param  Collection<int, EntityTurn>  $possibleTurns
     * @return Collection<int, EntityTurn>
     */
    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection;

    public function allowsEntityToBeCarriedTo(Entity $carrier, Entity $carriage, Cell $cell, CellPosition $cellPosition, TurnContext $turnContext): bool;
}
