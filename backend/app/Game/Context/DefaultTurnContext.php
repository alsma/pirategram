<?php

declare(strict_types=1);

namespace App\Game\Context;

use App\Game\Commands\Command;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class DefaultTurnContext implements TurnContext
{
    private Collection $appliedCommands;

    public function __construct(
        private readonly GameState $gameState,
    ) {
        $this->appliedCommands = collect();
    }

    public function applyCommand(Command $command): void
    {
        $this->appliedCommands->push($command);
    }

    public function getAppliedCommands(): Collection
    {
        return $this->appliedCommands;
    }
}
