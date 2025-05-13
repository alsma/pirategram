<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Commands\UpdateEntityStateCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityType;
use Illuminate\Support\Collection;

class TrapCellBehavior extends BaseCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $teammatePlayerIds = $turnContext->getTeammatePlayerIds();

        $teammatePirates = $turnContext->getEntities()
            ->filter(fn (Entity $e) => $e->type === EntityType::Pirate
                && $e->position->is($position)
                && $teammatePlayerIds->has($e->gamePlayerId)
                && $e->isNot($entity));

        if ($teammatePirates->isEmpty()) {
            $turnContext->applyCommand(UpdateEntityStateCommand::set($entity->id, EntityStateItem::StuckInTrap->value, true, __METHOD__));
        } else {
            $teammatePirates
                ->filter(fn (Entity $e) => $e->state->bool(EntityStateItem::StuckInTrap->value))
                ->each(function (Entity $e) use ($turnContext) {
                    $turnContext->applyCommand(UpdateEntityStateCommand::unset($e->id, EntityStateItem::StuckInTrap->value, __METHOD__));
                });
        }
    }

    public function processPossibleTurns(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        $entity = $turnContext->getTurnEntity();
        if ($entity->state->bool(EntityStateItem::StuckInTrap->value)) {
            $possibleTurns = collect();
        }

        return parent::processPossibleTurns($possibleTurns, $turnContext);
    }
}
