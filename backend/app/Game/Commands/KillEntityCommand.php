<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Context\TurnContext;
use App\Game\Data\EntityStateItem;

readonly class KillEntityCommand implements Command
{
    public function __construct(
        public string $entityId,
        public string $triggeredBy,
    ) {}

    public function execute(TurnContext $turnContext): void
    {
        $entity = $turnContext->getEntities()->getEntityByIdOrFail($this->entityId);

        $updatedEntity = $entity->updateState->set(EntityStateItem::IsKilled->value, true);
        $turnContext->updateEntity($updatedEntity);
    }
}
