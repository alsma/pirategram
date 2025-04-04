<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Behaviors\TurnAllowerCellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class FortressCellBehavior extends BaseCellBehavior implements TurnAllowerCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $teammatePlayerIds = $gameState->players
            ->where('team_id', $gameState->players->where('id', $entity->gamePlayerId)->firstOrFail()->team_id)
            ->pluck('id', 'id');

        $enemyPirates = $gameState->entities
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $e->position->is($position)
                && !$teammatePlayerIds->has($e->gamePlayerId));

        if ($enemyPirates->isNotEmpty()) {
            // Pirate can't come to fortress when another team's pirate is there
            // It's handled on processTurns level of pirate, but pirate still can move i.e. ice cell
            $updatedPirate = $entity->updateState->set(EntityStateItem::IsKilled->value, true);
            $gameState->entities = $gameState->entities->updateEntity($updatedPirate);
        }
    }

    public function allowsTurn(EntityTurn $turn, Entity $entity, Collection $entities, Context $context): bool
    {
        /** @var Collection<int, int> $teammatePlayerIds */
        $teammatePlayerIds = $context->mustGet('teammatePlayerIds');

        $enemyPirates = $entities
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $e->position->is($turn->position)
                && !$teammatePlayerIds->has($e->gamePlayerId));

        return $enemyPirates->isEmpty();
    }
}
