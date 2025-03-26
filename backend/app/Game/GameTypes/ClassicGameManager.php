<?php

declare(strict_types=1);

namespace App\Game\GameTypes;

use App\Exceptions\RuntimeException;
use App\Game\Data\CellPosition;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\Data\GameBoard;
use App\Game\Data\Vector;
use App\Game\GameTypes\Classic\BoardGenerator;
use App\Game\Models\GamePlayer;
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

    private const int MAX_RECURSIVE_TURNS = 10;

    public function generateBoard(): GameBoard
    {
        return $this->getBoardGenerator()->generateBoard();
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

    public function getAllowedTurns(GameBoard $gameBoard, Collection $entities, GamePlayer $turnPlayer): Collection
    {
        $boardGenerator = $this->getBoardGenerator();
        $turnContextData = $boardGenerator->getTurnContextData();

        $playerEntities = $entities->where('gamePlayerId', $turnPlayer->id);

        $enforcedTurnsEntityByIds = $playerEntities->reject(function (Entity $entity) use ($gameBoard) {
            $currentCell = $gameBoard->getCell($entity->position);
            $cellBehavior = $this->getCellBehavior($currentCell->type);

            return $cellBehavior->allowsEntityToStay();
        })->keyBy->id;

        return $playerEntities
            ->when($enforcedTurnsEntityByIds->isNotEmpty())->filter(fn (Entity $entity) => $enforcedTurnsEntityByIds->has($entity->id))
            ->map(function (Entity $entity) use ($gameBoard, $entities, $turnContextData) {
                $currentCell = $gameBoard->getCell($entity->position);
                $turnContext = new Context(array_merge($turnContextData, compact('currentCell')));

                $entityBehavior = $this->getEntityBehavior($entity->type);
                $cellBehavior = $this->getCellBehavior($currentCell->type);

                return Vector::createAroundVectors()
                    ->map(function (Vector $vector) use ($gameBoard, $entity) {
                        $position = $entity->position->add($vector);
                        $cell = $gameBoard->getCell($position);
                        if (!$cell) {
                            return null;
                        }

                        return new EntityTurn($entity->id, $cell, $position);
                    })
                    ->filter()
                    ->pipe(fn (Collection $possibleTurns) => $entityBehavior->processPossibleTurns($possibleTurns, $entity, $entities, $turnContext))
                    ->pipe(fn (Collection $possibleTurns) => $cellBehavior->processPossibleTurns($possibleTurns, $entity, $entities, $turnContext));
            })
            ->flatten();
    }

    public function processTurn(GameState $gameState, Entity $entity, CellPosition $position): void
    {
        $positionToMove = $position;
        $updatedEntity = $entity;
        $prevPosition = $updatedEntity->position;

        $iterations = 0;
        $finishTurn = true;

        do {
            // Detects deadlock and according to rules kill entity (it may happen only with pirates)
            if ($iterations > self::MAX_RECURSIVE_TURNS) {
                $updatedEntity = $entity->kill();
                $gameState->entities = $gameState->entities->updateEntity($updatedEntity);

                break;
            }

            // Update entity position and trigger related
            // actions such as pirates attack or move of ship with all pirates on board
            $entityBehavior = $this->getEntityBehavior($entity->type);
            $entityBehavior->move($gameState, $updatedEntity, $positionToMove);

            // Get target cell
            $cell = $gameState->board->getCell($positionToMove);

            // Retrieve updated entity by entity behavior
            // Store it's new position for recursive turns which depends on movement vector
            $updatedEntity = $gameState->entities->getEntityByIdOrFail($updatedEntity->id);
            $positionBeforeCellEnter = $updatedEntity->position;

            // Handle cell behavior, entity position may be updated inside, so that's why we use loop
            $cellBehavior = $this->getCellBehavior($cell->type);
            $cellBehavior->onEnter($gameState, $updatedEntity, $prevPosition, $cell, $positionToMove);
            $finishTurn = $cellBehavior->allowsEntityToStay();

            // Reveal cell
            $updatedCell = $gameState->board->getCell($positionToMove)->reveal();
            $gameState->board->setCell($positionToMove, $updatedCell);

            $prevPosition = $positionBeforeCellEnter;
            // Retrieve updated entity by cell behavior and check whether next loop iteration needed
            $updatedEntity = $gameState->entities->getEntityByIdOrFail($entity->id);
            $positionToMove = $updatedEntity->position;
            $iterations++;
        } while (!$positionBeforeCellEnter->is($positionToMove));

        if ($finishTurn) {
            $gameState->finalizeTurn();
        }
    }

    public function getBoardGenerator(): BoardGenerator
    {
        return new BoardGenerator;
    }
}
