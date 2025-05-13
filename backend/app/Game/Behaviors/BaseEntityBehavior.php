<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Context\TurnContext;
use Illuminate\Support\Collection;

abstract class BaseEntityBehavior implements EntityBehavior
{
    /** {@inheritDoc} */
    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        return $possibleTurns;
    }
}
