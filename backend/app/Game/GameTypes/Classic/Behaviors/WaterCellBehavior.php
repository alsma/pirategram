<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Commands\IncrementCoinsCollected;
use App\Game\Commands\RemoveEntityCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;

class WaterCellBehavior extends BaseCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        /** @var ?Entity $carriageEntity */
        $carriageEntity = $turnContext->getData()->get('carriageEntity');
        if (!$carriageEntity) {
            return;
        }

        $hasShipInPosition = $turnContext->getEntities()->contains(fn (Entity $e) => $e->type === EntityType::Ship &&
            $turnContext->getTeammatePlayerIds()->has($e->gamePlayerId) &&
            $e->position->is($position));

        if ($hasShipInPosition) {
            $teamId = $turnContext->getTurnPlayerTeamId();
            $turnContext->applyCommand(new IncrementCoinsCollected($teamId, __METHOD__));
        }

        $turnContext->applyCommand(new RemoveEntityCommand($carriageEntity->id, __METHOD__));
    }
}
