<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseEntityBehavior;
use App\Game\Commands\KillEntityCommand;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellPositionSet;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use Illuminate\Support\Collection;

class PirateEntityBehavior extends BaseEntityBehavior
{
    public function move(TurnContext $turnContext, Entity $entity, CellPosition $position): void
    {
        $turnContext->applyCommand(new UpdateEntityPositionCommand($entity->id, $position, __METHOD__.'(move)'));

        $teammatePlayerIds = $turnContext->getTeammatePlayerIds();
        $isEnemiesShip = $turnContext->getEntities()
            ->contains(fn (Entity $e) => $e->type === EntityType::Ship
                && !$teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($position));
        if ($isEnemiesShip) {
            $turnContext->applyCommand(new KillEntityCommand($entity->id, __METHOD__.'(enemy ship)'));

            return;
        }

        $turnContext->getEntities()
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && !$teammatePlayerIds->has($e->gamePlayerId)
                && $e->position->is($position))
            ->each(fn (Entity $e) => $turnContext->applyCommand(new KillEntityCommand($e->id, __METHOD__.'(killed by pirate)')));
    }

    /** {@inheritdoc} */
    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        $possibleTurns = parent::processPossibleTurns($possibleTurns, $turnContext);

        $contextData = $turnContext->getData();
        $entity = $turnContext->getTurnEntity();

        /** @var Cell $currentCell */
        $currentCell = $contextData->mustGet('currentCell');
        /** @var CellPositionSet $pirateWaterTurnBoundariesSet */
        $pirateWaterTurnBoundariesSet = $contextData->mustGet('pirateWaterTurnBoundariesSet');
        $teammatePlayerIds = $turnContext->getTeammatePlayerIds();

        $isOnShip = $turnContext->getEntities()->contains(fn (Entity $e) => $e->type === EntityType::Ship &&
            $teammatePlayerIds->has($e->gamePlayerId) &&
            $e->position->is($entity->position));
        $isInWater = !$isOnShip && $currentCell->type === CellType::Water;

        return $possibleTurns->filter(function (EntityTurn $turn) use ($pirateWaterTurnBoundariesSet, $isOnShip, $isInWater, $entity, $turnContext) {
            if ($turn->cell->type === CellType::Water) {
                if ($isInWater && $pirateWaterTurnBoundariesSet->exists($turn->position)) {
                    return false;
                }

                $hasShipInPosition = $turnContext->getEntities()->contains(fn (Entity $e) => $e->type === EntityType::Ship &&
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
