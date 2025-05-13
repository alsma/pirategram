<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Behaviors\TurnAllowerCellBehavior;
use App\Game\Commands\KillEntityCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;

class FortressCellBehavior extends BaseCellBehavior implements TurnAllowerCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $teammatePlayerIds = $turnContext->getTeammatePlayerIds();

        $enemyPirates = $turnContext->getEntities()
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $e->position->is($position)
                && !$teammatePlayerIds->has($e->gamePlayerId));

        if ($enemyPirates->isNotEmpty()) {
            // Pirate can't come to fortress when another team's pirate is there
            // It's handled on processTurns level of pirate, but pirate still can move i.e. ice cell
            $turnContext->applyCommand(new KillEntityCommand($entity->id, __METHOD__));
        }
    }

    public function allowsTurn(EntityTurn $turn, TurnContext $turnContext): bool
    {
        $teammatePlayerIds = $turnContext->getTeammatePlayerIds();

        $enemyPirates = $turnContext->getEntities()
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $e->position->is($turn->position)
                && !$teammatePlayerIds->has($e->gamePlayerId));

        return $enemyPirates->isEmpty();
    }

    public function allowsEntityToBeCarriedTo(Entity $carrier, Entity $carriage, Cell $cell, CellPosition $cellPosition, TurnContext $turnContext): bool
    {
        if ($carriage->type === EntityType::Coin) {
            return false;
        }

        return parent::allowsEntityToBeCarriedTo($carrier, $carriage, $cell, $cellPosition, $turnContext);
    }
}
