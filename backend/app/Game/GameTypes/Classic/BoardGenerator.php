<?php

declare(strict_types=1);

namespace App\Game\GameTypes\Classic;

use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellPositionSet;
use App\Game\Data\CellType;
use App\Game\Data\GameBoard;

class BoardGenerator
{
    private const int BOARD_COLS = 13;

    private const int BOARD_ROWS = 13;

    public function generateBoard(): GameBoard
    {
        $seed = str_random();
        $seedInt = (int) hexdec(substr(hash('sha256', $seed), 0, 8));
        mt_srand($seedInt);

        $result = new GameBoard(self::BOARD_ROWS, self::BOARD_COLS);

        $this->fillWaterCells($result);

        $cells = $this->getIslandCells();
        $this->fisherYatesShuffle($cells);
        $this->fillIslandCells($result, $cells);

        return $result;
    }

    public function getTurnContextData(): array
    {
        return [
            'shipTurnBoundariesSet' => $this->getShipTurnBoundariesSet(),
            'pirateWaterTurnBoundariesSet' => $this->getPirateWaterTurnBoundariesSet(),
        ];
    }

    public function createCellToReplaceSingleTimeUsageCells(): Cell
    {
        return new Cell(CellType::Terrain, true);
    }

    private function getIslandCells(): array
    {
        $result = collect();
        $result = $result->merge(array_fill(0, 40, CellType::Terrain));
        $result = $result->merge(array_fill(0, 3, CellType::Arrow1));
        $result = $result->merge(array_fill(0, 3, CellType::Arrow1Diagonal));
        $result = $result->merge(array_fill(0, 3, CellType::Arrow2));
        $result = $result->merge(array_fill(0, 3, CellType::Arrow2Diagonal));
        $result = $result->merge(array_fill(0, 3, CellType::Arrow3));
        $result = $result->merge(array_fill(0, 3, CellType::Arrow4));
        $result = $result->merge(array_fill(0, 3, CellType::Arrow4Diagonal));
        $result = $result->merge(array_fill(0, 2, CellType::Knight));
        $result = $result->merge(array_fill(0, 5, CellType::Labyrinth2));
        $result = $result->merge(array_fill(0, 4, CellType::Labyrinth3));
        $result = $result->merge(array_fill(0, 2, CellType::Labyrinth4));
        $result = $result->merge(array_fill(0, 1, CellType::Labyrinth5));
        $result = $result->merge(array_fill(0, 6, CellType::Ice));
        $result = $result->merge(array_fill(0, 3, CellType::Trap));
        $result = $result->merge(array_fill(0, 1, CellType::Ogre));
        $result = $result->merge(array_fill(0, 2, CellType::Fortress));
        $result = $result->merge(array_fill(0, 1, CellType::ReviveFortress));
        $result = $result->merge(array_fill(0, 5, CellType::Gold1));
        $result = $result->merge(array_fill(0, 5, CellType::Gold2));
        $result = $result->merge(array_fill(0, 3, CellType::Gold3));
        $result = $result->merge(array_fill(0, 2, CellType::Gold4));
        $result = $result->merge(array_fill(0, 1, CellType::Gold5));
        $result = $result->merge(array_fill(0, 1, CellType::Plane));
        $result = $result->merge(array_fill(0, 2, CellType::Balloon));
        $result = $result->merge(array_fill(0, 4, CellType::Barrel));
        $result = $result->merge(array_fill(0, 2, CellType::CannonBarrel));
        $result = $result->merge(array_fill(0, 4, CellType::Crocodile));

        return $result->all();
    }

    private function fillWaterCells(GameBoard $result): void
    {
        for ($col = 0; $col < self::BOARD_COLS; $col++) {
            $result->setCell(new CellPosition(0, $col), new Cell(CellType::Water, true));
            $result->setCell(new CellPosition(self::BOARD_ROWS - 1, $col), new Cell(CellType::Water, true));
        }

        for ($row = 0; $row < self::BOARD_ROWS; $row++) {
            $result->setCell(new CellPosition($row, 0), new Cell(CellType::Water, true));
            $result->setCell(new CellPosition($row, self::BOARD_COLS - 1), new Cell(CellType::Water, true));
        }

        $result->setCell(new CellPosition(1, 1), new Cell(CellType::Water, true));
        $result->setCell(new CellPosition(1, self::BOARD_COLS - 2), new Cell(CellType::Water, true));
        $result->setCell(new CellPosition(self::BOARD_ROWS - 2, 1), new Cell(CellType::Water, true));
        $result->setCell(new CellPosition(self::BOARD_ROWS - 2, self::BOARD_COLS - 2), new Cell(CellType::Water, true));
    }

    private function fisherYatesShuffle(array &$items): void
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $tmp;
        }
    }

    private function fillIslandCells(GameBoard $result, array $cells): void
    {
        foreach ($cells as $cellType) {
            $direction = match ($cellType) {
                CellType::Arrow1, CellType::Arrow1Diagonal,
                CellType::Arrow2, CellType::Arrow2Diagonal,
                CellType::Arrow3, CellType::CannonBarrel, => mt_rand(0, 3),
                default => null
            };

            $cell = new Cell($cellType, direction: $direction);
            $result->pushCell($cell);
        }
    }

    private function getShipTurnBoundariesSet(): CellPositionSet
    {
        return (new CellPositionSet)
            ->add(new CellPosition(0, 0))
            ->add(new CellPosition(0, 1))
            ->add(new CellPosition(1, 0))
            ->add(new CellPosition(1, 1))
            ->add(new CellPosition(11, 0))
            ->add(new CellPosition(11, 1))
            ->add(new CellPosition(12, 0))
            ->add(new CellPosition(12, 1))
            ->add(new CellPosition(11, 11))
            ->add(new CellPosition(12, 11))
            ->add(new CellPosition(11, 12))
            ->add(new CellPosition(12, 12))
            ->add(new CellPosition(0, 11))
            ->add(new CellPosition(0, 12))
            ->add(new CellPosition(1, 11))
            ->add(new CellPosition(1, 12));
    }

    private function getPirateWaterTurnBoundariesSet(): CellPositionSet
    {
        return (new CellPositionSet)
            ->add(new CellPosition(0, 1))
            ->add(new CellPosition(1, 0))
            ->add(new CellPosition(0, 0))
            ->add(new CellPosition(11, 0))
            ->add(new CellPosition(12, 1))
            ->add(new CellPosition(12, 0))
            ->add(new CellPosition(12, 11))
            ->add(new CellPosition(11, 12))
            ->add(new CellPosition(12, 12))
            ->add(new CellPosition(0, 11))
            ->add(new CellPosition(0, 12))
            ->add(new CellPosition(1, 12));
    }
}
