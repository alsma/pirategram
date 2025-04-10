<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use App\Game\Models\GameState;

class BalloonCellBehavior extends BaseCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        /** @var Entity $shipEntity */
        $shipEntity = $gameState->entities
            ->firstOrFail(fn (Entity $e) => $e->type === EntityType::Ship
                && $e->gamePlayerId === $entity->gamePlayerId);

        $updatedPirate = $entity->updatePosition($shipEntity->position);
        $gameState->entities = $gameState->entities->updateEntity($updatedPirate);
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }
}
