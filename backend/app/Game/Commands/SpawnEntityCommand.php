<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Context\TurnContext;
use App\Game\Data\Entity;

class SpawnEntityCommand implements Command
{
    public function __construct(
        public readonly Entity $entity,
        public readonly string $triggeredBy,
    ) {}

    public function execute(TurnContext $turnContext): void
    {
        $turnContext->mergeEntities(collect([$this->entity]));
    }
}
