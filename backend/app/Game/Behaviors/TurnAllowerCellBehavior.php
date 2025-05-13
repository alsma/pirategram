<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Context\TurnContext;
use App\Game\Data\EntityTurn;

interface TurnAllowerCellBehavior
{
    public function allowsTurn(EntityTurn $turn, TurnContext $turnContext): bool;
}
