<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;

class UpdateCellCommand implements Command
{
    public function __construct(
        public CellPosition $position,
        public Cell $cell,
        public string $triggeredBy,
    ) {}

    public function execute(TurnContext $turnContext): void
    {
        $turnContext->setCell($this->position, $this->cell);
    }
}
