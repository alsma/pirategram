<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Context\TurnContext;
use App\Game\Data\CellPosition;

readonly class UpdateEntityPositionCommand implements Command
{
    public function __construct(
        public string $entityId,
        public CellPosition $newPosition,
        public string $triggeredBy,
        public bool $safe = false,
    ) {}

    public function execute(TurnContext $turnContext): void
    {
        if ($this->safe) {
            $entity = $turnContext->getEntities()->getEntityById($this->entityId);

            if (!$entity) {
                return;
            }
        } else {
            $entity = $turnContext->getEntities()->getEntityByIdOrFail($this->entityId);
        }

        $updatedEntity = $entity->updatePosition($this->newPosition);
        $turnContext->updateEntity($updatedEntity);
    }
}
