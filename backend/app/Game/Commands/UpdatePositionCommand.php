<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Data\CellPosition;
use App\Game\Models\GameState;

readonly class UpdatePositionCommand implements Command
{
    public function __construct(
        public string $entityId,
        public CellPosition $newPosition,
        public string $triggeredBy,
    ) {}

    public function execute(GameState $gameState): void
    {
        $entity = $gameState->entities->getEntityByIdOrFail($this->entityId);

        $updatedEntity = $entity->updatePosition($this->newPosition);
        $gameState->entities = $gameState->entities->updateEntity($updatedEntity);
    }
}
