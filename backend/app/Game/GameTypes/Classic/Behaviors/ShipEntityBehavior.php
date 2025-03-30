<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseEntityBehavior;
use App\Game\Data\CellPosition;
use App\Game\Data\CellPositionSet;
use App\Game\Data\CellType;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class ShipEntityBehavior extends BaseEntityBehavior
{
    public function move(GameState $gameState, Entity $entity, CellPosition $position): void
    {
        $teammatePlayerIds = $gameState->players
            ->where('team_id', $gameState->players->where('id', $entity->gamePlayerId)->firstOrFail()->team_id)
            ->pluck('id', 'id');

        $updatePiratesOnShip = $gameState->entities
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($entity->position))
            ->map->updatePosition($position);

        $updatedShip = $entity->updatePosition($position);

        $killedEnemies = $gameState->entities
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && !$teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($position))
            ->map(fn (Entity $e) => $e->updateState($entity->state->set(EntityStateItem::IsKilled->value, true)));

        $updatedEntities = collect()
            ->merge($updatePiratesOnShip)
            ->push($updatedShip)
            ->merge($killedEnemies);

        $gameState->entities = $gameState->entities->updateEntities($updatedEntities);
    }

    /** {@inheritDoc} */
    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection
    {
        $possibleTurns = parent::processPossibleTurns($possibleTurns, $entity, $entities, $context);

        /** @var CellPositionSet $shipBoundariesSet */
        $shipBoundariesSet = $context->mustGet('shipTurnBoundariesSet');

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
