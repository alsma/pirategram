<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityCollection;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityType;
use App\Game\Data\GameBoard;
use App\Game\GameTypes\ClassicGameManager;
use Tests\Fakes\InMemoryTurnContext;
use Tests\TestCase;

class ClassicGameTypeManagerTest extends TestCase
{
    private ClassicGameManager $gameManager;

    private GameBoard $gameBoard;

    private int $turnPlayerId;

    private int $turnPlayerTeamId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameManager = $this->app->make(ClassicGameManager::class);
        $this->gameBoard = new GameBoard(13, 13);
        $this->turnPlayerId = random_int(0, 1000);
        $this->turnPlayerTeamId = random_int(0, 2);
    }

    public function test_pirate_can_only_move_straight_from_ship(): void
    {
        $shipPosition = new CellPosition(6, 0);
        $pirate = new Entity(EntityType::Pirate, $shipPosition, $this->turnPlayerId);
        $ship = new Entity(EntityType::Ship, $shipPosition, $this->turnPlayerId);
        $entities = new EntityCollection([$pirate, $ship]);

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

        $allowedTurns = $this->gameManager->getAllowedTurns(InMemoryTurnContext::createSimpleContext($this->gameBoard, $this->turnPlayerId, $this->turnPlayerTeamId, $entities));

        $expectedMoves = collect([
            new CellPosition(6, 1),
        ]);

        $actualMoves = $allowedTurns->where('entityId', $pirate->id)->map->position;

        $this->assertEqualsCanonicalizing($expectedMoves, $actualMoves);
    }

    public function test_pirate_moves_on_land_normally(): void
    {
        $piratePosition = new CellPosition(6, 6);
        $pirate = new Entity(EntityType::Pirate, $piratePosition, $this->turnPlayerId);
        $entities = new EntityCollection([$pirate]);

        $this->gameBoard->setCell(new CellPosition(5, 5), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(5, 6), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(5, 7), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(6, 5), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(6, 6), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(6, 7), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(7, 5), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(7, 6), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(7, 7), new Cell(CellType::Terrain));

        $allowedTurns = $this->gameManager->getAllowedTurns(InMemoryTurnContext::createSimpleContext($this->gameBoard, $this->turnPlayerId, $this->turnPlayerTeamId, $entities));

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

        $actualMoves = $allowedTurns->map->position;

        $this->assertEqualsCanonicalizing($expectedMoves, $actualMoves);
    }

    public function test_ship_moves_only_on_water(): void
    {
        $shipPosition = new CellPosition(6, 0);
        $ship = new Entity(EntityType::Ship, $shipPosition, $this->turnPlayerId);
        $entities = new EntityCollection([$ship]);

        $this->gameBoard->setCell(new CellPosition(6, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(6, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(5, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(7, 0), new Cell(CellType::Water));
        $this->gameBoard->setCell(new CellPosition(4, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(5, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(6, 1), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(7, 1), new Cell(CellType::Terrain));

        $allowedTurns = $this->gameManager->getAllowedTurns(InMemoryTurnContext::createSimpleContext($this->gameBoard, $this->turnPlayerId, $this->turnPlayerTeamId, $entities));

        $expectedMoves = collect([
            new CellPosition(5, 0),
            new CellPosition(7, 0),
        ]);

        $actualMoves = $allowedTurns->map->position;

        $this->assertEqualsCanonicalizing($expectedMoves, $actualMoves);
    }

    public function test_moves_an_entity_normally(): void
    {
        $this->gameBoard->setCell(new CellPosition(2, 2), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Terrain));

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);
        $entities = new EntityCollection([$entity]);

        $turnContext = new InMemoryTurnContext($this->gameBoard, $this->turnPlayerId, $this->turnPlayerTeamId, $entity, new CellPosition(3, 2), collect(), $entities);

        $this->gameManager->processTurn($turnContext);

        $this->assertEquals(new CellPosition(3, 2), $turnContext->getTurnEntity()->position);
    }

    public function test_triggers_cell_behavior_on_enter(): void
    {
        $this->gameBoard->setCell(new CellPosition(2, 2), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Ice));
        $this->gameBoard->setCell(new CellPosition(4, 2), new Cell(CellType::Terrain));

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);
        $entities = new EntityCollection([$entity]);

        $turnContext = new InMemoryTurnContext($this->gameBoard, $this->turnPlayerId, $this->turnPlayerTeamId, $entity, new CellPosition(3, 2), collect(), $entities);

        // Ice cell that should slide entity
        $this->gameManager->processTurn($turnContext);

        $this->assertEquals(new CellPosition(4, 2), $turnContext->getTurnEntity()->position);
    }

    public function test_handles_infinite_loops_by_killing_entity(): void
    {
        $this->gameBoard->setCell(new CellPosition(2, 2), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Arrow1, direction: 1));
        $this->gameBoard->setCell(new CellPosition(4, 2), new Cell(CellType::Arrow1, direction: 3));

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);
        $entities = new EntityCollection([$entity]);

        $turnContext = new InMemoryTurnContext($this->gameBoard, $this->turnPlayerId, $this->turnPlayerTeamId, $entity, new CellPosition(3, 2), collect(), $entities);

        $this->gameManager->processTurn($turnContext);

        $this->assertTrue($turnContext->getTurnEntity()->state->bool(EntityStateItem::IsKilled->value));
    }

    public function test_handles_recursive_cell_behavior(): void
    {
        $this->gameBoard->setCell(new CellPosition(2, 2), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Ice));
        $this->gameBoard->setCell(new CellPosition(4, 2), new Cell(CellType::Arrow1, direction: 3));

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);
        $entities = new EntityCollection([$entity]);

        $turnContext = new InMemoryTurnContext($this->gameBoard, $this->turnPlayerId, $this->turnPlayerTeamId, $entity, new CellPosition(3, 2), collect(), $entities);

        $this->gameManager->processTurn($turnContext);

        $this->assertEquals(new CellPosition(2, 2), $turnContext->getTurnEntity()->position);
    }

    public function test_reveals_cells_when_entity_enters(): void
    {
        $this->gameBoard->setCell(new CellPosition(2, 2), new Cell(CellType::Terrain));
        $this->gameBoard->setCell(new CellPosition(3, 2), new Cell(CellType::Gold1));

        $entity = new Entity(EntityType::Pirate, new CellPosition(2, 2), $this->turnPlayerId);
        $entities = new EntityCollection([$entity]);

        $turnContext = new InMemoryTurnContext($this->gameBoard, $this->turnPlayerId, $this->turnPlayerTeamId, $entity, new CellPosition(3, 2), collect(), $entities);

        $this->gameManager->processTurn($turnContext);

        $updatedEntity = $turnContext->getTurnEntity();
        $this->assertEquals(new CellPosition(3, 2), $updatedEntity->position);

        $updatedCell = $turnContext->getCell($updatedEntity->position);
        $this->assertTrue($updatedCell->revealed);
    }
}
