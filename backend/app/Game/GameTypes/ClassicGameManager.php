<?php

declare(strict_types=1);

namespace App\Game\GameTypes;

use App\Exceptions\LocalizedException;
use App\Exceptions\RuntimeException;
use App\Game\Behaviors\TurnAllowerCellBehavior;
use App\Game\Behaviors\TurnOverHandlerCellBehavior;
use App\Game\Data\CellPosition;
use App\Game\Data\Context;
use App\Game\Data\Entity;
use App\Game\Data\EntityCollection;
use App\Game\Data\EntityStateItem;
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

    public function getAllowedTurns(GameState $gameState, GamePlayer $turnPlayer): Collection
    {
        $gameBoard = $gameState->board;
        $entities = $gameState->entities;

        $playerEntities = $entities->where('gamePlayerId', $turnPlayer->id)
            ->reject(fn (Entity $e) => $e->state->bool(EntityStateItem::IsKilled->value));

        $enforcedTurnsEntityByIds = $playerEntities->reject(function (Entity $entity) use ($gameBoard) {
            $currentCell = $gameBoard->getCell($entity->position);
            $cellBehavior = $this->getCellBehavior($currentCell->type);

            return $cellBehavior->allowsEntityToStay();
        })->keyBy->id;

        $teammatePlayerIds = $gameState->players
            ->where('team_id', $turnPlayer->team_id)
            ->pluck('id', 'id');

        $boardGenerator = $this->getBoardGenerator();
        $turnContextData = array_merge($boardGenerator->getTurnContextData(), compact('gameBoard', 'teammatePlayerIds'));

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
                    ->pipe(fn (Collection $possibleTurns) => $cellBehavior->processPossibleTurns($possibleTurns, $entity, $entities, $turnContext))
                    ->pipe(fn (Collection $possibleTurns) => $possibleTurns->filter(function (EntityTurn $entityTurn) use ($entity, $entities, $turnContext) {
                        // Process turns by final cell behaviors
                        if (!$this->isCellBehaviorContract($entityTurn->cell->type, TurnAllowerCellBehavior::class)) {
                            return true;
                        }

                        /** @var TurnAllowerCellBehavior $cellBehavior */
                        $cellBehavior = $this->getCellBehavior($entityTurn->cell->type);

                        return $cellBehavior->allowsTurn($entityTurn, $entity, $entities, $turnContext);
                    }))
                    ->pipe(fn (Collection $possibleTurns) => $this->processPossibleTurnsCanCarry($possibleTurns, $entity, $entities, $turnContext));
            })
            ->flatten();
    }

    public function processTurn(GameState $gameState, Entity $entity, CellPosition $position, array $params): void
    {
        $allowedTurns = $this->getAllowedTurns($gameState, $gameState->currentTurn);

        $turn = $allowedTurns->firstWhere(fn (EntityTurn $turn) => $turn->entityId === $entity->id && $turn->position->is($position));
        if (!$turn) {
            throw new LocalizedException('game_invalid_turn_position');
        }

        $carriageEntity = $this->extractCarriageEntity($gameState, $entity, $params);
        $this->checkCarriageAllowedForTurn($turn, $carriageEntity);

        $positionToMove = $position;
        $updatedEntity = $entity;
        $prevPosition = $updatedEntity->position;

        $iterations = 0;

        do {
            // Detects deadlock and according to rules kill entity (it may happen only with pirates)
            if ($iterations > self::MAX_RECURSIVE_TURNS) {
                $updatedEntity = $entity->updateState->set(EntityStateItem::IsKilled->value, true);
                $gameState->entities = $gameState->entities->updateEntity($updatedEntity);

                // if deadlock detected and pirate died, return coin to position before turn
                if ($carriageEntity) {
                    $updatedCarriageEntity = $carriageEntity->updatePosition($prevPosition);
                    $gameState->entities = $gameState->entities->updateEntity($updatedCarriageEntity);
                }

                $finalizeTurn = true;

                break;
            }

            $prevCell = $gameState->board->getCell($prevPosition);
            $prevCellBehavior = $this->getCellBehavior($prevCell->type);
            $prevCellBehavior->onLeave($gameState, $updatedEntity, $prevPosition, $prevCell, $positionToMove);
            // Retrieve updated entity by entity behavior
            // TODO we can't take updated position here as it breaks some cell behaviors like ice/crocodile
            $updatedEntity = $gameState->entities->getEntityByIdOrFail($updatedEntity->id);

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
            $finalizeTurn = $cellBehavior->allowsEntityToStay();

            // Reveal cell
            $updatedCell = $gameState->board->getCell($positionToMove)->reveal();
            $gameState->board->setCell($positionToMove, $updatedCell);

            $prevPosition = $positionBeforeCellEnter;
            // Retrieve updated entity by cell behavior and check whether next loop iteration needed
            $updatedEntity = $gameState->entities->getEntityByIdOrFail($entity->id);
            $positionToMove = $updatedEntity->position;
            $iterations++;
        } while ($positionBeforeCellEnter->isNot($positionToMove));

        if ($carriageEntity) {
            $updatedCarriageEntity = $carriageEntity->updatePosition($updatedEntity->position);
            $gameState->entities = $gameState->entities->updateEntity($updatedCarriageEntity);
        }

        $gameState->entities
            ->each(function (Entity $e) use ($gameState, $entity) {
                if ($e->gamePlayerId !== $entity->gamePlayerId) {
                    return;
                } elseif ($e->id === $entity->id) {
                    return;
                } elseif ($e->state->bool(EntityStateItem::IsKilled->value)) {
                    return;
                }

                $cell = $gameState->board->getCell($e->position);
                if ($this->isCellBehaviorContract($cell->type, TurnOverHandlerCellBehavior::class)) {
                    /** @var TurnOverHandlerCellBehavior $cellBehavior */
                    $cellBehavior = $this->getCellBehavior($cell->type);
                    $cellBehavior->onPlayerTurnOver($gameState, $e, $cell, $e->position);
                }
            });

        if ($finalizeTurn) {
            $gameState->finalizeTurn();
        }
    }

    public function getBoardGenerator(): BoardGenerator
    {
        return new BoardGenerator;
    }

    private function extractCarriageEntity(GameState $gameState, Entity $entity, array $params): ?Entity
    {
        $carriageEntityId = $params['carriageEntityId'] ?? null;
        if (!$carriageEntityId) {
            return null;
        }

        $carriageEntity = $gameState->entities->firstWhere('id', $carriageEntityId);
        if (!$carriageEntity) {
            throw new LocalizedException('game_carriage_entity_not_found');
        }

        if ($carriageEntity->type !== EntityType::Coin) {
            throw new LocalizedException('game_carriage_entity_invalid');
        }

        if ($entity->position->isNot($carriageEntity->position)) {
            throw new LocalizedException('game_carriage_entity_invalid');
        }

        return $carriageEntity;
    }

    private function checkCarriageAllowedForTurn(EntityTurn $turn, ?Entity $carriageEntity): void
    {
        if (!$carriageEntity) {
            return;
        }

        if (!$turn->canCarry($carriageEntity->id)) {
            throw new LocalizedException('game_carriage_entity_not_allowed');
        }
    }

    /**
     * @param  Collection<EntityTurn>  $possibleTurns
     * @return Collection<EntityTurn>
     */
    private function processPossibleTurnsCanCarry(Collection $possibleTurns, Entity $entity, EntityCollection $entities, Context $turnContext): Collection
    {
        $carriableEntities = $entities->filter(fn (Entity $e) => $e->position->is($entity->position) && $this->entityCanBeCarried($e->type));
        if ($carriableEntities->isEmpty()) {
            return $possibleTurns;
        }

        return $possibleTurns
            ->map(function (EntityTurn $turn) use ($entity, $turnContext, $carriableEntities) {
                $canBeCarriedEntityIds = $carriableEntities->filter(function (Entity $carried) use ($entity, $turn, $turnContext) {
                    $cellBehavior = $this->getCellBehavior($turn->cell->type);

                    return $cellBehavior->allowsEntityToBeCarriedTo($entity, $carried, $turn->cell, $turn->position, $turnContext);
                })->map->id->all();

                if ($canBeCarriedEntityIds) {
                    return $turn->allowCarry($canBeCarriedEntityIds);
                }

                return $turn;
            });
    }

    private function entityCanBeCarried(EntityType $entityType): bool
    {
        return in_array($entityType, [EntityType::Coin], true);
    }
}
