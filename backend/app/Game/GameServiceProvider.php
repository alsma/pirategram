<?php

declare(strict_types=1);

namespace App\Game;

use App\Game\Data\CellType;
use App\Game\Data\EntityType;
use App\Game\Data\GameType;
use App\Game\GameTypes\Classic\Behaviors\BalloonCellBehavior;
use App\Game\GameTypes\Classic\Behaviors\CannonBarrelCellBehavior;
use App\Game\GameTypes\Classic\Behaviors\CrocodileCellBehavior;
use App\Game\GameTypes\Classic\Behaviors\GoldCellBehavior;
use App\Game\GameTypes\Classic\Behaviors\IceCellBehavior;
use App\Game\GameTypes\Classic\Behaviors\MultiDirectionArrowBehavior;
use App\Game\GameTypes\Classic\Behaviors\OgreCellBehavior;
use App\Game\GameTypes\Classic\Behaviors\PirateEntityBehavior;
use App\Game\GameTypes\Classic\Behaviors\ShipEntityBehavior;
use App\Game\GameTypes\Classic\Behaviors\SingleArrowCellBehavior;
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

        $this->app->singleton(ClassicGameManager::class, function () {
            $instance = new ClassicGameManager;
            $instance->setBehaviorResolver(fn (string $className) => $this->app->make($className));
            $instance->registerEntityBehavior(EntityType::Ship, ShipEntityBehavior::class);
            $instance->registerEntityBehavior(EntityType::Pirate, PirateEntityBehavior::class);
            $instance->registerCellBehavior(CellType::Gold1, GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Gold2, GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Gold3, GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Gold4, GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Gold5, GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Crocodile, CrocodileCellBehavior::class);
            $instance->registerCellBehavior(CellType::Balloon, BalloonCellBehavior::class);
            $instance->registerCellBehavior(CellType::Ogre, OgreCellBehavior::class);
            $instance->registerCellBehavior(CellType::Ice, IceCellBehavior::class);
            $instance->registerCellBehavior(CellType::CannonBarrel, CannonBarrelCellBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow1, SingleArrowCellBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow1Diagonal, SingleArrowCellBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow2, MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow2Diagonal, MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow3, MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow3, MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow4, MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow4Diagonal, MultiDirectionArrowBehavior::class);

            return $instance;
        });
    }
}
