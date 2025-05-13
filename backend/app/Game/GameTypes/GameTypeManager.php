<?php

declare(strict_types=1);

namespace App\Game\GameTypes;

use App\Game\Context\TurnContext;
use App\Game\Data\EntityCollection;
use App\Game\Data\GameBoard;
use Illuminate\Support\Collection;

interface GameTypeManager
{
    public function generateBoard(): GameBoard;

    public function generateEntities(Collection $players): EntityCollection;

    public function getAllowedTurns(TurnContext $turnContext): Collection;

    public function processTurn(TurnContext $turnContext): void;
}
