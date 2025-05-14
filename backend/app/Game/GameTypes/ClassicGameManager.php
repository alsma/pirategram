<?php

declare(strict_types=1);

namespace App\Game\GameTypes;

use App\Exceptions\LocalizedException;
use App\Exceptions\RuntimeException;
use App\Game\Behaviors\TurnAllowerCellBehavior;
use App\Game\Behaviors\TurnOverHandlerCellBehavior;
use App\Game\Commands\KillEntityCommand;
use App\Game\Commands\UpdateEntityPositionCommand;
use App\Game\Context\TurnContext;
use App\Game\Data\CellPosition;
use App\Game\Data\ContextData;
use App\Game\Data\Entity;
use App\Game\Data\EntityCollection;
use App\Game\Data\EntityStateItem;
use App\Game\Data\EntityTurn;
use App\Game\Data\EntityType;
use App\Game\Data\GameBoard;
use App\Game\Data\Vector;
use App\Game\GameTypes\Classic\BoardGenerator;
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

    public function generateEntities(Collection $players): EntityCollection
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
            $spawns->push(new CellPosition(12, 6));
            $spawns->push(new CellPosition(6, 12));
            $spawns->push(new CellPosition(0, 6));
        } else {
            throw new RuntimeException('Unexpected number of players.');
        }

        $players = $players->sortBy('order', SORT_NUMERIC)->values();
        $spawns->reduce(function (Collection $entities, CellPosition $spawn, int $idx) use ($players) {
            $entities->push(new Entity(EntityType::Ship, $spawn, $players[$idx]->id));
            $entities->push(new Entity(EntityType::Pirate, $spawn, $players[$idx]->id));
            $entities->push(new Entity(EntityType::Pirate, $spawn, $players[$idx]->id));
            $entities->push(new Entity(EntityType::Pirate, $spawn, $players[$idx]->id));

            return $entities;
        }, $entities);

        return new EntityCollection($entities);
    }

    public function getAllowedTurns(TurnContext $turnContext): Collection
    {
        $playerEntities = $turnContext->getEntities()
            ->where(fn (Entity $entity) => $entity->gamePlayerId === $turnContext->getTurnPlayerId()
                && !$entity->state->bool(EntityStateItem::IsKilled->value));

        $enforcedTurnsEntityByIds = $playerEntities->reject(function (Entity $entity) use ($turnContext) {
            $currentCell = $turnContext->getCell($entity->position);
            $cellBehavior = $this->getCellBehavior($currentCell->type);

            return $cellBehavior->allowsEntityToStay();
        })->keyBy->id;

        $turnContext->mergeData($this->getBoardGenerator()->getTurnContextData());

        return $playerEntities
            ->toBase()
            ->when($enforcedTurnsEntityByIds->isNotEmpty())->filter(fn (Entity $entity) => $enforcedTurnsEntityByIds->has($entity->id))
            ->map(function (Entity $entity) use ($turnContext) {
                $currentCell = $turnContext->getCell($entity->position);
                $turnContext->setTurnEntity($entity);
                $turnContext->mergeData(new ContextData(compact('currentCell')));

                $entityBehavior = $this->getEntityBehavior($entity->type);
                $cellBehavior = $this->getCellBehavior($currentCell->type);

                return Vector::createAroundVectors()
                    ->map(function (Vector $vector) use ($turnContext, $entity) {
                        $position = $entity->position->add($vector);
                        $cell = $turnContext->getCell($position);
                        if (!$cell) {
                            return null;
                        }

                        return new EntityTurn($entity->id, $cell, $position);
                    })
                    ->filter()
                    ->pipe(fn (Collection $possibleTurns) => $entityBehavior->processPossibleTurns($possibleTurns, $turnContext))
                    ->pipe(fn (Collection $possibleTurns) => $cellBehavior->processPossibleTurns($possibleTurns, $turnContext))
                    ->pipe(fn (Collection $possibleTurns) => $possibleTurns->filter(function (EntityTurn $entityTurn) use ($turnContext) {
                        // Process turns by final cell behaviors
                        if (!$this->isCellBehaviorContract($entityTurn->cell->type, TurnAllowerCellBehavior::class)) {
                            return true;
                        }

                        /** @var TurnAllowerCellBehavior $cellBehavior */
                        $cellBehavior = $this->getCellBehavior($entityTurn->cell->type);

                        return $cellBehavior->allowsTurn($entityTurn, $turnContext);
                    }))
                    ->pipe(fn (Collection $possibleTurns) => $this->processPossibleTurnsCanCarry($possibleTurns, $turnContext));
            })
            ->flatten();
    }

    public function processTurn(TurnContext $turnContext): void
    {
        $allowedTurns = $this->getAllowedTurns(clone $turnContext);

        $entity = $turnContext->getTurnEntity();
        $position = $turnContext->getTurnPosition();

        $turn = $allowedTurns->firstWhere(fn (EntityTurn $turn) => $turn->entityId === $entity->id && $turn->position->is($position));
        if (!$turn) {
            throw new LocalizedException('game_invalid_turn_position');
        }

        $carriageEntity = $this->extractCarriageEntity($turnContext);
        $this->checkCarriageAllowedForTurn($turn, $carriageEntity);

        $turnContext->mergeData(new ContextData(compact('carriageEntity')));

        $positionToMove = $position;
        $updatedEntity = $entity;
        $prevPosition = $initialPosition = $updatedEntity->position;

        $iterations = 0;

        do {
            // Detects deadlock and according to rules kill entity (it may happen only with pirates)
            if ($iterations > self::MAX_RECURSIVE_TURNS) {
                $turnContext->applyCommand(new KillEntityCommand($entity->id, __METHOD__.'(max recursive turns)'));

                // if deadlock detected and pirate died, return coin to position before turn
                if ($carriageEntity) {
                    $turnContext->applyCommand(new UpdateEntityPositionCommand($carriageEntity->id, $prevPosition, __METHOD__.'(max recursive turns)', safe: true));
                }

                $finalizeTurn = true;

                break;
            }

            $prevCell = $turnContext->getCell($prevPosition);
            $prevCellBehavior = $this->getCellBehavior($prevCell->type);
            $prevCellBehavior->onLeave($turnContext, $updatedEntity, $prevPosition, $prevCell, $positionToMove);
            // Retrieve updated entity by entity behavior
            // TODO we can't take updated position here as it breaks some cell behaviors like ice/crocodile
            $updatedEntity = $turnContext->getEntities()->getEntityByIdOrFail($updatedEntity->id);

            // Update entity position and trigger related
            // actions such as pirates attack or move of ship with all pirates on board
            $entityBehavior = $this->getEntityBehavior($entity->type);
            $entityBehavior->move($turnContext, $updatedEntity, $positionToMove);

            // Get target cell
            $cell = $turnContext->getCell($positionToMove);

            // Retrieve updated entity by entity behavior
            // Store it's new position for recursive turns which depends on movement vector
            $updatedEntity = $turnContext->getEntities()->getEntityByIdOrFail($updatedEntity->id);
            $positionBeforeCellEnter = $updatedEntity->position;

            // Handle cell behavior, entity position may be updated inside, so that's why we use loop
            $cellBehavior = $this->getCellBehavior($cell->type);
            $cellBehavior->onEnter($turnContext, $updatedEntity, $prevPosition, $cell, $positionToMove);
            $finalizeTurn = $cellBehavior->allowsEntityToStay();

            // Reveal cell
            $updatedCell = $turnContext->getCell($positionToMove)->reveal();
            $turnContext->setCell($positionToMove, $updatedCell);

            $prevPosition = $positionBeforeCellEnter;
            // Retrieve updated entity by cell behavior and check whether next loop iteration needed
            $updatedEntity = $turnContext->getTurnEntity();
            $positionToMove = $updatedEntity->position;
            $iterations++;
        } while ($positionBeforeCellEnter->isNot($positionToMove));

        if ($carriageEntity) {
            $turnContext->applyCommand(new UpdateEntityPositionCommand($carriageEntity->id, $updatedEntity->position, __METHOD__.'(carriage)', safe: true));
        }

        $turnContext->getEntities()
            ->each(function (Entity $e) use ($turnContext, $entity) {
                if ($e->gamePlayerId !== $entity->gamePlayerId) {
                    return;
                } elseif ($e->id === $entity->id) {
                    return;
                } elseif ($e->state->bool(EntityStateItem::IsKilled->value)) {
                    return;
                }

                $cell = $turnContext->getCell($e->position);
                if ($this->isCellBehaviorContract($cell->type, TurnOverHandlerCellBehavior::class)) {
                    /** @var TurnOverHandlerCellBehavior $cellBehavior */
                    $cellBehavior = $this->getCellBehavior($cell->type);
                    $cellBehavior->onPlayerTurnOver($turnContext, $e, $cell, $e->position);
                }
            });

        if ($finalizeTurn) {
            $turnContext->finalizeTurn();
        } else {
            $allowedTurns = $this->getAllowedTurns(clone $turnContext);

            $turn = $allowedTurns->firstWhere('entityId', $entity->id);
            if (!$turn) {
                $turnContext->applyCommand(new KillEntityCommand($entity->id, __METHOD__.'(no more possible turns)'));

                if ($carriageEntity) {
                    $turnContext->applyCommand(new UpdateEntityPositionCommand($carriageEntity->id, $initialPosition, __METHOD__.'(no more possible turns)'));
                }
            }
        }
    }

    public function getBoardGenerator(): BoardGenerator
    {
        return new BoardGenerator;
    }

    private function extractCarriageEntity(TurnContext $turnContext): ?Entity
    {
        $carriageEntityId = $turnContext->getData()->get('carriageEntityId');
        if (!$carriageEntityId) {
            return null;
        }

        $carriageEntity = $turnContext->getEntities()->firstWhere('id', $carriageEntityId);
        if (!$carriageEntity) {
            throw new LocalizedException('game_carriage_entity_not_found');
        }

        if ($carriageEntity->type !== EntityType::Coin) {
            throw new LocalizedException('game_carriage_entity_invalid');
        }

        if ($turnContext->getTurnEntity()->position->isNot($carriageEntity->position)) {
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
    private function processPossibleTurnsCanCarry(Collection $possibleTurns, TurnContext $turnContext): Collection
    {
        $entity = $turnContext->getTurnEntity();
        $entities = $turnContext->getEntities();

        $carriableEntities = $entities
            ->filter(fn (Entity $e) => $e->position->is($entity->position) && $this->entityCanBeCarried($e->type));
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
