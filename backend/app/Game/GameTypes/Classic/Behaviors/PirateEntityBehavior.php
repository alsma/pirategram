<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseEntityBehavior;
use App\Game\Data\Cell;
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

class PirateEntityBehavior extends BaseEntityBehavior
{
    public function move(GameState $gameState, Entity $entity, CellPosition $position): void
    {
        $teammatePlayerIds = $gameState->players
            ->where('team_id', $gameState->players->where('id', $entity->gamePlayerId)->firstOrFail()->team_id)
            ->pluck('id', 'id');

        $updatedPirate = $entity->updatePosition($position);

        $isEnemiesShip = $gameState->entities
            ->contains(fn (Entity $e) => $e->type === EntityType::Ship
                && !$teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($position));
        if ($isEnemiesShip) {
            $updatedPirate = $entity->updateState($entity->state->set(EntityStateItem::IsKilled->value, true));
            $gameState->entities = $gameState->entities->updateEntity($updatedPirate);

            return;
        }

        $killedEnemies = $gameState->entities
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && !$teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($position))
            ->map(fn (Entity $e) => $e->updateState($entity->state->set(EntityStateItem::IsKilled->value, true)));

        $updatedEntities = collect()
            ->merge($killedEnemies)
            ->push($updatedPirate);

        $gameState->entities = $gameState->entities->updateEntities($updatedEntities);
    }

    /** {@inheritdoc} */
    public function processPossibleTurns(Collection $possibleTurns, Entity $entity, Collection $entities, Context $context): Collection
    {
        $possibleTurns = parent::processPossibleTurns($possibleTurns, $entity, $entities, $context);

        /** @var Cell $currentCell */
        $currentCell = $context->mustGet('currentCell');
        /** @var CellPositionSet $pirateWaterTurnBoundariesSet */
        $pirateWaterTurnBoundariesSet = $context->mustGet('pirateWaterTurnBoundariesSet');
        /** @var Collection<int, int> $teammatePlayerIds */
        $teammatePlayerIds = $context->mustGet('teammatePlayerIds');

        $isOnShip = $entities->contains(fn (Entity $e) => $e->type === EntityType::Ship &&
            $teammatePlayerIds->has($e->gamePlayerId) &&
            $e->position->is($entity->position));
        $isInWater = !$isOnShip && $currentCell->type === CellType::Water;

        return $possibleTurns->filter(function (EntityTurn $turn) use ($pirateWaterTurnBoundariesSet, $isOnShip, $entities, $isInWater, $entity) {
            if ($turn->cell->type === CellType::Water) {
                if ($isInWater && $pirateWaterTurnBoundariesSet->exists($turn->position)) {
                    return false;
                }

                $hasShipInPosition = $entities->contains(fn (Entity $e) => $e->type === EntityType::Ship &&
                    $e->gamePlayerId === $entity->gamePlayerId &&
                    $e->position->is($turn->position));
                if (!($isInWater || $hasShipInPosition)) {
                    return false;
                }
            } else {
                $vector = $turn->position->difference($entity->position);
                if ($isOnShip && (abs($vector->col) + abs($vector->row) !== 1)) {
                    // Restrict diagonal moves ONLY if the pirate is on a ship
                    return false;
                } elseif ($isInWater) {
                    // Restrict moves from water to ground
                    return false;
                }
            }

            return true;
        });
    }
}
