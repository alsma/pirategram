<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\TurnOverHandlerCellBehavior;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\Commands\UpdateEntityStateCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityType;
use Illuminate\Support\Collection;

class FortressReviveCellBehavior extends FortressCellBehavior implements TurnOverHandlerCellBehavior
{
    public function onPlayerTurnOver(TurnContext $turnContext, Entity $entity, Cell $cell, CellPosition $position): void
    {
        $teammatePlayerIds = $turnContext->getTeammatePlayerIds();

        /** @var Collection<int, Entity> $killedPirates */
        $killedPirates = $turnContext->getEntities()
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $teammatePlayerIds->has($e->gamePlayerId)
                && $e->state->bool(EntityStateItem::IsKilled->value));

        if (!$killedPirates->isEmpty()) {
            $revivedPirate = $killedPirates->firstWhere('gamePlayerId', $turnContext->getTurnPlayerId()) ?? $killedPirates->first();
            $turnContext->applyCommand(UpdateEntityStateCommand::unset($revivedPirate->id, EntityStateItem::IsKilled->value, __METHOD__));
            $turnContext->applyCommand(new UpdateEntityPositionCommand($revivedPirate->id, $entity->position, __METHOD__));
        }
    }
}
