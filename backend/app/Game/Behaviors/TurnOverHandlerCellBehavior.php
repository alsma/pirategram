<?php

declare(strict_types=1);

namespace App\Game\Behaviors;

use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;

interface TurnOverHandlerCellBehavior
{
    /**
     * This method will be executed for all player entities except the one player made turn with
     */
    public function onPlayerTurnOver(TurnContext $turnContext, Entity $entity, Cell $cell, CellPosition $position): void;
}
