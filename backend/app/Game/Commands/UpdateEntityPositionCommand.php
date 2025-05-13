<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Context\TurnContext;
use App\Game\Data\CellPosition;

readonly class UpdatePositionCommand implements Command
{
    public function __construct(
        public string $entityId,
        public CellPosition $newPosition,
        public string $triggeredBy,
    ) {}

    public function execute(TurnContext $turnContext): void
    {
        $entity = $turnContext->getEntities()->getEntityByIdOrFail($this->entityId);

        $updatedEntity = $entity->updatePosition($this->newPosition);
        $turnContext->updateEntity($updatedEntity);
    }
}
