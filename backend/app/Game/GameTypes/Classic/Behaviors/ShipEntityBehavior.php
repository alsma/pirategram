<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseEntityBehavior;
use App\Game\Commands\KillEntityCommand;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\CellPosition;
use App\Game\Data\CellPositionSet;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use Illuminate\Support\Collection;

class ShipEntityBehavior extends BaseEntityBehavior
{
    public function move(TurnContext $turnContext, Entity $entity, CellPosition $position): void
    {
        $turnContext->applyCommand(new UpdateEntityPositionCommand($entity->id, $position, __METHOD__.'(move)'));

        $teammatePlayerIds = $turnContext->getTeammatePlayerIds();

        $turnContext->getEntities()
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($entity->position))
            ->each(fn (Entity $e) => $turnContext->applyCommand(new UpdateEntityPositionCommand($e->id, $position, __METHOD__.'(moved by ship)')));

        $turnContext->getEntities()
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && !$teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($position))
            ->each(fn (Entity $e) => $turnContext->applyCommand(new KillEntityCommand($e->id, __METHOD__.'(killed by ship)')));

    }

    /** {@inheritDoc} */
    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        $possibleTurns = parent::processPossibleTurns($possibleTurns, $turnContext);

        /** @var CellPositionSet $shipBoundariesSet */
        $shipBoundariesSet = $turnContext->getData()->mustGet('shipTurnBoundariesSet');

        return $possibleTurns->filter(function (EntityTurn $turn) use ($shipBoundariesSet) {
            if ($turn->cell->type !== CellType::Water) {
                return false;
            }

            if ($shipBoundariesSet->exists($turn->position)) {
                return false;
            }

            return true;
        });
    }
}
