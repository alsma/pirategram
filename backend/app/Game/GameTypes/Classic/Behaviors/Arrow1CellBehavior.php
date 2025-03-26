<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Exceptions\RuntimeException;
use App\Game\Behaviors\CellBehavior;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\Vector;
use App\Game\Models\GameState;

class Arrow1CellBehavior implements CellBehavior
{
    public function onEnter(GameState $gameState, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        if ($cell->type === CellType::Arrow1) {
            $vector = match ($cell->direction) {
                0 => new Vector(0, -1),
                1 => new Vector(1, 0),
                2 => new Vector(0, 1),
                3 => new Vector(-1, 0),
            };
        } elseif ($cell->type === CellType::Arrow1Diagonal) {
            $vector = match ($cell->direction) {
                0 => new Vector(1, -1),
                1 => new Vector(1, 1),
                2 => new Vector(-1, 1),
                3 => new Vector(-1, -1),
            };
        } else {
            throw new RuntimeException('Unexpected cell type.');
        }

        $newPosition = $entity->position->add($vector);
        $updatedPirate = $entity->updatePosition($newPosition);

        $updatedEntities = collect();
        $updatedEntities->push($updatedPirate);

        $gameState->entities = $gameState->entities->updateEntities($updatedEntities);
    }
}
