<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Context\TurnContext;

readonly class RemoveEntityCommand implements Command
{
    public function __construct(
        public string $entityId,
        public string $triggeredBy,
    ) {}

    public function execute(TurnContext $turnContext): void
    {
        $turnContext->removeEntity($turnContext->getEntities()->getEntityByIdOrFail($this->entityId));
    }
}
