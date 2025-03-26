<?php

declare(strict_types=1);

namespace App\Game\GameTypes;

use App\Game\Data\CellPosition;
use App\Game\Data\Entity;
use App\Game\Data\GameBoard;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

interface GameTypeManager
{
    public function generateBoard(): GameBoard;

    public function generateEntities(Collection $players): Collection;

    public function getAllowedTurnsForEntities(GameBoard $gameBoard, Collection $entities): Collection;

    public function processTurn(GameState $gameState, Entity $entity, CellPosition $position): void;
}
