<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\Data\GameBoard;
use App\Game\GameTypes\ClassicGameManager;
use Tests\TestCase;

class ClassicGameTypeManagerTest extends TestCase
{
    private ClassicGameManager $gameManager;

    private GameBoard $gameBoard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameManager = $this->app->make(ClassicGameManager::class);
        $this->gameBoard = new GameBoard(13, 13);
    }

    public function test_pirate_can_only_move_straight_from_ship(): void
    {
        $shipPosition = new CellPosition(6, 0);
        $pirate = new Entity(EntityType::Pirate, $shipPosition);
        $ship = new Entity(EntityType::Ship, $shipPosition);

        $this->gameBoard->setCell(new CellPosition(4, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(5, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(6, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(7, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(8, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(4, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(5, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(6, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(7, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(8, 1), new Cell(CellType::Terrain));

        $entities = collect([$pirate, $ship]);
        $allowedTurns = $this->gameManager->getAllowedTurnsForEntities($this->gameBoard, $entities);

        $expectedMoves = collect([
            new CellPosition(6, 1),
        ]);

        $actualMoves = $allowedTurns->where('entityId', $pirate->id)->map(fn (EntityTurn $turn) => $turn->cellPosition);

        $this->assertEqualsCanonicalizing($expectedMoves, $actualMoves);
    }

    public function test_pirate_moves_on_land_normally(): void
    {
        $piratePosition = new CellPosition(6, 6);
        $pirate = new Entity(EntityType::Pirate, $piratePosition);

        $this->gameBoard->setCell(new CellPosition(5, 5), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(5, 6), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(5, 7), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(6, 5), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(6, 7), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(7, 5), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(7, 6), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(7, 7), new Cell(CellType::Terrain));

        $entities = collect([$pirate]);
        $allowedTurns = $this->gameManager->getAllowedTurnsForEntities($this->gameBoard, $entities);

        $expectedMoves = collect([
            new CellPosition(5, 5),
            new CellPosition(5, 6),
            new CellPosition(5, 7),
            new CellPosition(6, 5),
            new CellPosition(6, 7),
            new CellPosition(7, 5),
            new CellPosition(7, 6),
            new CellPosition(7, 7),
        ]);

        $actualMoves = $allowedTurns->map(fn (EntityTurn $turn) => $turn->cellPosition);

        $this->assertEqualsCanonicalizing($expectedMoves, $actualMoves);
    }

    public function test_ship_moves_only_on_water(): void
    {
        $shipPosition = new CellPosition(6, 0);
        $ship = new Entity(EntityType::Ship, $shipPosition);

        $this->gameBoard->setCell(new CellPosition(6, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(6, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(5, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(7, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(4, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(5, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(6, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(7, 1), new Cell(CellType::Terrain));

        $entities = collect([$ship]);
        $allowedTurns = $this->gameManager->getAllowedTurnsForEntities($this->gameBoard, $entities);

        $expectedMoves = collect([
            new CellPosition(5, 0),
            new CellPosition(7, 0),
        ]);

        $actualMoves = $allowedTurns->map(fn (EntityTurn $turn) => $turn->cellPosition);

        $this->assertEqualsCanonicalizing($expectedMoves, $actualMoves);
    }
}
