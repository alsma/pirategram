<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Context\TurnContext;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;

class NullEntityBehavior extends BaseEntityBehavior
{
    public function move(TurnContext $turnContext, Entity $entity, CellPosition $position): void {}
}
