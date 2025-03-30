<?php

declare(strict_types=1);

namespace App\Game;

use App\Game\Data\CellType;
use App\Game\Data\EntityType;
use App\Game\Data\GameType;
use App\Game\GameTypes\Classic\Behaviors as ClassicBehaviors;
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

        $this->registerClassGameServices();
    }

    private function registerClassGameServices(): void
    {
        $this->app->singleton(ClassicGameManager::class, function () {
            $instance = new ClassicGameManager;
            $instance->setBehaviorResolver(fn (string $className) => $this->app->make($className));
            $instance->registerEntityBehavior(EntityType::Ship, ClassicBehaviors\ShipEntityBehavior::class);
            $instance->registerEntityBehavior(EntityType::Pirate, ClassicBehaviors\PirateEntityBehavior::class);
            $instance->registerCellBehavior(CellType::Gold1, ClassicBehaviors\GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Gold2, ClassicBehaviors\GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Gold3, ClassicBehaviors\GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Gold4, ClassicBehaviors\GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Gold5, ClassicBehaviors\GoldCellBehavior::class);
            $instance->registerCellBehavior(CellType::Crocodile, ClassicBehaviors\CrocodileCellBehavior::class);
            $instance->registerCellBehavior(CellType::Balloon, ClassicBehaviors\BalloonCellBehavior::class);
            $instance->registerCellBehavior(CellType::Ogre, ClassicBehaviors\OgreCellBehavior::class);
            $instance->registerCellBehavior(CellType::Ice, ClassicBehaviors\IceCellBehavior::class);
            $instance->registerCellBehavior(CellType::CannonBarrel, ClassicBehaviors\CannonBarrelCellBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow1, ClassicBehaviors\SingleArrowCellBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow1Diagonal, ClassicBehaviors\SingleArrowCellBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow2, ClassicBehaviors\MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow2Diagonal, ClassicBehaviors\MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow3, ClassicBehaviors\MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow4, ClassicBehaviors\MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Arrow4Diagonal, ClassicBehaviors\MultiDirectionArrowBehavior::class);
            $instance->registerCellBehavior(CellType::Plane, ClassicBehaviors\PlaneCellBehavior::class);
            $instance->registerCellBehavior(CellType::Knight, ClassicBehaviors\KnightCellBehavior::class);
            $instance->registerCellBehavior(CellType::Barrel, ClassicBehaviors\BarrelCellBehavior::class);
            $instance->registerCellBehavior(CellType::Labyrinth2, ClassicBehaviors\LabyrinthCellBehavior::class);
            $instance->registerCellBehavior(CellType::Labyrinth3, ClassicBehaviors\LabyrinthCellBehavior::class);
            $instance->registerCellBehavior(CellType::Labyrinth4, ClassicBehaviors\LabyrinthCellBehavior::class);
            $instance->registerCellBehavior(CellType::Labyrinth5, ClassicBehaviors\LabyrinthCellBehavior::class);
            $instance->registerCellBehavior(CellType::Trap, ClassicBehaviors\TrapCellBehavior::class);
            $instance->registerCellBehavior(CellType::Fortress, ClassicBehaviors\FortressCellBehavior::class);
            $instance->registerCellBehavior(CellType::ReviveFortress, ClassicBehaviors\FortressReviveCellBehavior::class);

            return $instance;
        });
    }
}
