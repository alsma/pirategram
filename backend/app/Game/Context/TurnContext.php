<?php

declare(strict_types=1);

namespace App\Game\Context;

use App\Game\Commands\Command;
use Illuminate\Support\Collection;

interface TurnContext
{
    public function applyCommand(Command $command): void;

    public function getAppliedCommands(): Collection;
}
