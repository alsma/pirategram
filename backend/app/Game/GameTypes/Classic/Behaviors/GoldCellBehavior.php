<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic\Behaviors;

use App\Exceptions\RuntimeException;
use App\Game\Behaviors\BaseCellBehavior;
use App\Game\Commands\SpawnEntityCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityType;
use Illuminate\Support\Collection;

class GoldCellBehavior extends BaseCellBehavior
{
    public function onEnter(TurnContext $turnContext, Entity $entity, CellPosition $prevPosition, Cell $cell, CellPosition $position): void
    {
        if ($cell->revealed) {
            return;
        }

        $coinsCnt = match ($cell->type) {
            CellType::Gold1 => 1,
            CellType::Gold2 => 2,
            CellType::Gold3 => 3,
            CellType::Gold4 => 4,
            CellType::Gold5 => 5,
            default => throw new RuntimeException("Unexpected cell type '{$cell->type->value}'.")
        };

        Collection::range(1, $coinsCnt)
            ->each(fn () => $turnContext->applyCommand(new SpawnEntityCommand(new Entity(EntityType::Coin, $position), __METHOD__)));
    }
}
