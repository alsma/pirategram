<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityStateItem;
use App\Game\Data\GameBoard;
use App\Game\Models\GameState;

class IceCellBehavior extends BaseCellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        $vector = $entity->position->difference($prevPosition);
        $newPosition = $entity->position->add($vector);

        if ($gameState->board->hasCell($newPosition)) {
            $updatedPirate = $entity->updatePosition($newPosition);
        } else {
            // handle knight x2 behavior and plane behavior
            $updatedPirate = $entity->updateState->set(EntityStateItem::IsKilled->value, true);
        }

        $gameState->entities = $gameState->entities->updateEntity($updatedPirate);
    }

    public function allowsEntityToStay(): bool
    {
        return false;
    }

    public function allowsEntityToBeCarriedTo(Entity $carrier, Entity $carriage, Cell $cell, CellPosition $cellPosition, Context $context): bool
    {
        /** @var GameBoard $gameBoard */
        $gameBoard = $context->mustGet('gameBoard');
        $vector = $cellPosition->difference($carrier->position);
        $newPosition = $cellPosition->add($vector);

        $newCell = $gameBoard->getCell($newPosition);
        if ($newCell) {
            return parent::allowsEntityToBeCarriedTo($carrier, $carriage, $newCell, $newPosition, $context);
        }

        return false;
    }
}
