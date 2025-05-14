<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Classic\Behaviors;

use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityCollection;
use App\Game\Data\EntityType;
use App\Game\Data\GameBoard;
use Tests\Fakes\InMemoryTurnContext;

trait TurnContextBuilderTrait
{
    protected int $turnPlayerId = 1;

    protected int $turnPlayerTeamId = 1;

    protected int $teammatePlayerId = 2;

    protected function create2x2TurnContext(): TurnContext
    {
        $gameBoard = new GameBoard(5, 5);
        $this->fillCells($gameBoard);

        $enemyPlayerId1 = 3;
        $enemyPlayerId2 = 4;

        $playerShip = new Entity(EntityType::Ship, new CellPosition(2, 0), $this->turnPlayerId);
        $teammateShip = new Entity(EntityType::Ship, new CellPosition(2, 4), $this->teammatePlayerId);
        $enemyShip1 = new Entity(EntityType::Ship, new CellPosition(0, 2), $enemyPlayerId1);
        $enemyShip2 = new Entity(EntityType::Ship, new CellPosition(4, 2), $enemyPlayerId2);

        $entities = new EntityCollection([
            $playerShip,
            $teammateShip,
            $enemyShip1,
            $enemyShip2,
        ]);

        return new InMemoryTurnContext(
            $gameBoard,
            $this->turnPlayerId,
            $this->turnPlayerTeamId,
            new Entity(EntityType::Null, new CellPosition(0, 0), $this->turnPlayerId),
            new CellPosition(0, 0),
            collect([$this->turnPlayerId, $this->teammatePlayerId]),
            $entities,
        );
    }

    private function fillCells(GameBoard $gameBoard): void
    {
        $waterCells = 0;
        $maxCells = ($gameBoard->rows + 1) * ($gameBoard->cols + 1);

        for ($col = 0; $col < $gameBoard->cols; $col++) {
            $gameBoard->setCell(new CellPosition(0, $col), new Cell(CellType::Water, true));
            $gameBoard->setCell(new CellPosition($gameBoard->rows - 1, $col), new Cell(CellType::Water, true));
            $waterCells += 2;
        }

        for ($row = 0; $row < $gameBoard->rows; $row++) {
            $gameBoard->setCell(new CellPosition($row, 0), new Cell(CellType::Water, true));
            $gameBoard->setCell(new CellPosition($row, $gameBoard->cols - 1), new Cell(CellType::Water, true));
            $waterCells += 2;
        }

        for ($c = 0; $c <= ($maxCells - $waterCells); $c++) {
            $gameBoard->pushCell(new Cell(CellType::Terrain));
        }
    }
}
