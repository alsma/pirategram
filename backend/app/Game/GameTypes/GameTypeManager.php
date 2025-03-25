<?php

declare(strict_types=1);

namespace App\Game\GameTypes;

use App\Game\Data\GameBoard;
use Illuminate\Support\Collection;

interface GameTypeManager
{
    public function generateBoard(): GameBoard;

    public function generateEntities(Collection $players): Collection;

    public function getAllowedTurnsForEntities(GameBoard $gameBoard, Collection $entities): Collection;
}
