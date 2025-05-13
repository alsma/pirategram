<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;

class BalloonCellBehavior extends BaseCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        /** @var Entity $shipEntity */
        $shipEntity = $turnContext->getEntities()
            ->firstOrFail(fn (Entity $e) => $e->type === EntityType::Ship
                && $e->gamePlayerId === $entity->gamePlayerId);

        $turnContext->applyCommand(new UpdateEntityPositionCommand($entity->id, $shipEntity->position, __METHOD__));
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }
}
