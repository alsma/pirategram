<?php

declare(strict_types=1);

namespace App\Game\GameTypes;

use App\Exceptions\RuntimeException;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\CellPositionSet;
use App\Game\Data\CellType;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\Data\GameBoard;
use App\Game\Data\Vector;
use App\Game\Models\GameState;
use App\Game\Support\BehaviorsAwareTrait;
use Illuminate\Support\Collection;

/**
 * Classic rules is board 11x11 without corners
 * Island consists of 117 cells
 * Total board size 13x13 or 169 cells
 */
class ClassicGameManager implements GameTypeManager
{
    use BehaviorsAwareTrait;

    private const int BOARD_COLS = 13;

    private const int BOARD_ROWS = 13;

    public function generateBoard(): GameBoard
    {
        $seed = str_random();
        $seedInt = (int)hexdec(substr(hash('sha256', $seed), 0, 8));
        mt_srand($seedInt);

        $result = new GameBoard(self::BOARD_ROWS, self::BOARD_COLS);

        $this->fillWaterCells($result);

        $cells = $this->getIslandCells();
        $this->fisherYatesShuffle($cells);
        $this->fillIslandCells($result, $cells);

        return $result;
    }

    public function generateEntities(Collection $players): Collection
    {
        $entities = collect();
        $spawns = collect();
        if ($players->count() === 1) {
            // single player mode
            $spawns->push(new CellPosition(6, 12));
        } elseif ($players->count() === 2) {
            // 1x1 mode
            $spawns->push(new CellPosition(6, 0));
            $spawns->push(new CellPosition(6, 12));
        } elseif ($players->count() === 4) {
            // 2x2 mode or 4 Free-for-all
            $spawns->push(new CellPosition(6, 0));
            $spawns->push(new CellPosition(6, 12));
            $spawns->push(new CellPosition(0, 6));
            $spawns->push(new CellPosition(12, 6));
        } else {
            throw new RuntimeException('Unexpected number of players.');
        }

        $players = $players->sortBy('team_id', SORT_NUMERIC)->values();
        $spawns->reduce(function (Collection $entities, CellPosition $spawn, int $idx) use ($players) {
            $entities->push(new Entity(EntityType::Ship, $spawn, $players[$idx]->id));
            $entities->push(new Entity(EntityType::Pirate, $spawn, $players[$idx]->id));
            $entities->push(new Entity(EntityType::Pirate, $spawn, $players[$idx]->id));
            $entities->push(new Entity(EntityType::Pirate, $spawn, $players[$idx]->id));

            return $entities;
        }, $entities);

        return $entities;
    }

    public function getAllowedTurnsForEntities(GameBoard $gameBoard, Collection $entities): Collection
    {
        return $entities
            ->map(function (Entity $entity) use ($entities, $gameBoard) {
                $nearestCells = Vector::createAroundVectors()
                    ->map(function (Vector $vector) use ($gameBoard, $entity) {
                        $cellPosition = $entity->position->add($vector);

                        $cell = $gameBoard->getCell($cellPosition);
                        if (!$cell) {
                            return null;
                        }

                        return [$cellPosition, $cell];
                    })
                    ->filter();

                return match ($entity->type) {
                    EntityType::Ship => $this->getAllowedTurnsForShip($nearestCells, $entity),
                    EntityType::Pirate => $this->getAllowedTurnsForPirate($nearestCells, $entity, $gameBoard, $entities),
                    default => collect(),
                };
            })
            ->flatten();
    }

    public function processTurn(GameState $gameState, Entity $entity, CellPosition $position): void
    {
        $positionToMove = $position;
        $updatedEntity = $entity;

        do {
            $prevPosition = $updatedEntity->position;

            $this->getEntityBehavior($entity->type)->move($gameState, $updatedEntity, $positionToMove);
            $updatedEntity = $gameState->entities->firstOrFail('id', $updatedEntity->id);

            $cell = $gameState->board->getCell($positionToMove);

            $positionBeforeCellEnter = $updatedEntity->position;
            $this->getCellBehavior($cell->type)->onEnter($gameState, $updatedEntity, $prevPosition, $cell, $positionToMove);
            $updatedEntity = $gameState->entities->firstOrFail('id', $entity->id);

            $updatedCell = $gameState->board->getCell($positionToMove)->reveal();
            $gameState->board->setCell($positionToMove, $updatedCell);

            $positionToMove = $updatedEntity->position;
        } while (!$positionBeforeCellEnter->is($positionToMove));
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

    private function getAllowedTurnsForShip(Collection $nearestCells, Entity $entity): Collection
    {
        $shipBoundaries = $this->getShipTurnBoundariesSet();

        return $nearestCells->map(function (array $turnData) use ($entity, $shipBoundaries) {
            [$cellPosition, $cell] = $turnData;

            if ($cell->type !== CellType::Water) {
                return null;
            }

            if ($shipBoundaries->exists($cellPosition)) {
                return null;
            }

            return new EntityTurn($entity->id, $cellPosition);
        })->filter();
    }

    private function getAllowedTurnsForPirate(Collection $nearestCells, Entity $entity, GameBoard $gameBoard, Collection $entities): Collection
    {
        $pirateWaterBoundaries = $this->getPirateWaterTurnBoundariesSet();

        $isOnShip = $entities->contains(fn(Entity $e) => $e->type === EntityType::Ship && $e->position->is($entity->position));
        $isInWater = !$isOnShip && $gameBoard->getCell($entity->position)?->type === CellType::Water;

        return $nearestCells->map(function (array $turnData) use ($pirateWaterBoundaries, $isInWater, $isOnShip, $entities, $entity) {
            [$cellPosition, $cell] = $turnData;

            if ($cell->type === CellType::Water) {
                if ($isInWater && $pirateWaterBoundaries->exists($cellPosition)) {
                    return null;
                }

                $hasShipInPosition = $entities->contains(fn(Entity $e) => $e->type === EntityType::Ship && $e->position->is($cellPosition));
                if (!($isInWater || $hasShipInPosition)) {
                    return null;
                }
            } else {
                $vector = $cellPosition->difference($entity->position);
                if ($isOnShip && (abs($vector->col) + abs($vector->row) !== 1)) {
                    // Restrict diagonal moves ONLY if the pirate is on a ship
                    return null;
                } elseif ($isInWater) {
                    // Restrict moves from water to ground
                    return null;
                }
            }

            return new EntityTurn($entity->id, $cellPosition, $cell->revealed);
        })->filter();
    }
}
