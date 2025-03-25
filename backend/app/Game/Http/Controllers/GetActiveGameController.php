<?php

declare(strict_types=1);

namespace App\Game\Http\Controllers;

use App\Game\GameManager;
use App\Game\Http\Resources\GameStateResource;

class GetActiveGameController
{
    public function __invoke(GameManager $gameManager): GameStateResource
    {
        return GameStateResource::make($gameManager->getActiveGame())->withAllowedTurns();
    }
}
