<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityCollection;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\Data\GameBoard;
use App\Game\Data\GameType;
use App\Game\GameTypes\ClassicGameManager;
use App\Game\Models\GamePlayer;
use App\Game\Models\GameState;
use App\User\Models\User;
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

    public function test_moves_an_entity_normally(): void
    {
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Terrain));

        [$gameState, $player] = $this->createTestGameState();

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $player->id);
        $gameState->entities = new EntityCollection([$entity]);

        $this->gameManager->processTurn($gameState, $entity, new CellPosition(3, 2));

        $updatedEntity = $gameState->entities->getEntityByIdOrFail($entity->id);
        $this->assertEquals(new CellPosition(3, 2), $updatedEntity->position);
    }

    public function test_triggers_cell_behavior_on_enter(): void
    {
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Ice));
        $this->gameBoard->setCell(new CellPosition(4, 2), new Cell(CellType::Terrain));

        [$gameState, $player] = $this->createTestGameState();

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $player->id);
        $gameState->entities = new EntityCollection([$entity]);

        // Ice cell that should slide entity
        $this->gameManager->processTurn($gameState, $entity, new CellPosition(3, 2));

        $updatedEntity = $gameState->entities->getEntityByIdOrFail($entity->id);
        $this->assertEquals(new CellPosition(4, 2), $updatedEntity->position);
    }

    public function test_handles_infinite_loops_by_killing_entity(): void
    {
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Arrow1, direction: 1));
        $this->gameBoard->setCell(new CellPosition(4, 2), new Cell(CellType::Arrow1, direction: 3));

        [$gameState, $player] = $this->createTestGameState();

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $player->id);
        $gameState->entities = new EntityCollection([$entity]);

        $this->gameManager->processTurn($gameState, $entity, new CellPosition(3, 2));

        $updatedEntity = $gameState->entities->getEntityByIdOrFail($entity->id);
        $this->assertTrue($updatedEntity->isKilled);
    }

    public function test_handles_recursive_cell_behavior(): void
    {
        $this->gameBoard->setCell(new CellPosition(2, 2), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Ice));
        $this->gameBoard->setCell(new CellPosition(4, 2), new Cell(CellType::Arrow1, direction: 3));

        [$gameState, $player] = $this->createTestGameState();

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $player->id);
        $gameState->entities = new EntityCollection([$entity]);

        $this->gameManager->processTurn($gameState, $entity, new CellPosition(3, 2));

        $updatedEntity = $gameState->entities->getEntityByIdOrFail($entity->id);
        $this->assertEquals(new CellPosition(2, 2), $updatedEntity->position);
    }

    public function test_reveals_cells_when_entity_enters(): void
    {
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Gold1));

        [$gameState, $player] = $this->createTestGameState();

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $player->id);
        $gameState->entities = new EntityCollection([$entity]);

        $this->gameManager->processTurn($gameState, $entity, new CellPosition(3, 2));

        $updatedEntity = $gameState->entities->getEntityByIdOrFail($entity->id);
        $this->assertEquals(new CellPosition(3, 2), $updatedEntity->position);

        $updatedCell = $gameState->board->getCell($updatedEntity->position);
        $this->assertTrue($updatedCell->revealed);
    }

    private function createTestGameState(): array
    {
        $gameState = new GameState;
        $gameState->type = GameType::Classic;
        $gameState->board = $this->gameBoard;
        $gameState->save();

        $player = new GamePlayer;
        $player->user()->associate(User::factory()->create());
        $player->gameState()->associate($gameState);
        $player->order = 0;
        $player->team_id = 0;
        $player->save();

        return [$gameState, $player];
    }
}
