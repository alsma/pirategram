<?php

declare(strict_types=1);

namespace App\Game\Commands;

use App\Game\Models\GameState;

interface Command
{
    public function execute(GameState $gameState): void;
}
