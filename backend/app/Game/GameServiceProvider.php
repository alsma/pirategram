<?php

declare(strict_types=1);

namespace App\Game;

use App\Game\Data\GameType;
use App\Game\GameTypes\ClassicGameManager;
use Illuminate\Support\ServiceProvider;

class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameManager::class, function () {
            $instance = new GameManager;
            $instance->setGameTypeManagerResolver(fn (string $gameClass) => $this->app->make($gameClass));
            $instance->registerGameTypeManager(GameType::Classic, ClassicGameManager::class);

            return $instance;
        });
    }
}
