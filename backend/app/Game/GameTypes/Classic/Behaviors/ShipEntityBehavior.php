<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseEntityBehavior;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use App\Game\Models\GameState;

class ShipEntityBehavior extends BaseEntityBehavior
{
    public function move(GameState $game, Entity $entity, CellPosition $position): void
    {
        $teammatePlayerIds = $game->players
            ->where('team_id', $game->players->where('id', $entity->gamePlayerId)->firstOrFail()->team_id)
            ->pluck('id', 'id');

        $updatePiratesOnShip = $game->entities
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($entity->position))
            ->map->updatePosition($position);

        $updatedShip = $entity->updatePosition($position);

        $killedEnemies = $game->entities
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && !$teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($position))
            ->map->kill();

        $updatedEntities = collect()
            ->merge($updatePiratesOnShip)
            ->push($updatedShip)
            ->merge($killedEnemies);

        $game->entities = $game->entities->updateEntities($updatedEntities);
    }
}
