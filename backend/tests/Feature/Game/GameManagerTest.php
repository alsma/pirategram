<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Game\GameManager;
use Tests\TestCase;

class GameManagerTest extends TestCase
{
    private GameManager $gameManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameManager = $this->app->make(GameManager::class);
    }

    public function test_new_game(): void
    {
        $game = $this->gameManager->newGame();
    }
}
