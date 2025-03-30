<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use Illuminate\Support\Collection;

interface TurnAllowerCellBehavior
{
    public function allowsTurn(EntityTurn $turn, Entity $entity, Collection $entities, Context $context): bool;
}
