<?php

declare(strict_types=1);

namespace App\Game\Http\Controllers;

use App\Game\GameManager;
use App\Game\Http\Requests\MakeTurnRequest;
use App\Game\Http\Resources\GameStateResource;

class MakeTurnController
{
    public function __invoke(GameManager $gameManager, MakeTurnRequest $request): GameStateResource
    {
        $gameState = $gameManager->makeTurn(
            $request->getGame(),
            $request->user(),
            $request->getEntityId(),
            $request->getPosition()
        );

        return GameStateResource::make($gameState)->withAllowedTurns();
    }
}
