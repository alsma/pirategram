<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Context\TurnContext;

interface Command
{
    public function execute(TurnContext $turnContext): void;
}
