<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\TurnOverHandlerCellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityType;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class FortressReviveCellBehavior extends FortressCellBehavior implements TurnOverHandlerCellBehavior
{
    public function onPlayerTurnOver(GameState $gameState, Entity $entity, Cell $cell, CellPosition $position): void
    {
        /** @var Collection<int, Entity> $killedPirates */
        $killedPirates = $gameState->entities
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $e->gamePlayerId === $entity->gamePlayerId
                && $e->state->bool(EntityStateItem::IsKilled->value));

        if (!$killedPirates->isEmpty()) {
            $revivedPirate = $killedPirates->first()
                ->updateState
                ->unset(EntityStateItem::IsKilled->value)
                ->updatePosition($entity->position);
            $gameState->entities = $gameState->entities->updateEntity($revivedPirate);
        }
    }
}
