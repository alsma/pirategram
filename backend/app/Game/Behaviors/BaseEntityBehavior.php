<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Data\Context;
use App\Game\Data\Entity;
use Illuminate\Support\Collection;

abstract class BaseEntityBehavior implements EntityBehavior
{
    /** {@inheritDoc} */
    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection
    {
        return $possibleTurns;
    }
}
