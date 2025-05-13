<?php

declare(strict_types=1);

namespace App\Game\Context;

use App\Game\Commands\Command;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\ContextData;
use App\Game\Data\Entity;
use App\Game\Data\EntityCollection;
use App\Game\Data\EntityType;
use App\Game\Models\GameState;
use Illuminate\Support\Collection;

class DefaultTurnContext implements TurnContext
{
    private Collection $appliedCommands;

    public static function createFromGameState(GameState $gameState): self
    {
        return new self(
            $gameState,
            new Entity(EntityType::Null, new CellPosition(0, 0)),
            new CellPosition(0, 0),
            new ContextData([])
        );
    }

    public function __construct(
        private readonly GameState $gameState,
        private Entity $entity,
        private CellPosition $position,
        private ContextData $data,
    ) {
        $this->appliedCommands = collect();
    }

    public function getTurnPlayerId(): int
    {
        return $this->gameState->current_turn_game_player_id;
    }

    public function getTeammatePlayerIds(): Collection
    {
        return $this->gameState->players
            ->where('team_id', $this->gameState->currentTurn->team_id)
            ->pluck('id', 'id');
    }

    public function setTurnEntity(Entity $entity): TurnContext
    {
        $this->entity = $entity;

        return $this;
    }

    public function getTurnEntity(): Entity
    {
        return $this->getEntities()->getEntityByIdOrFail($this->entity->id);
    }

    public function getEntities(): EntityCollection
    {
        return $this->gameState->entities;
    }

    public function updateEntity(Entity $updatedEntity): void
    {
        $this->gameState->entities = $this->gameState->entities->updateEntity($updatedEntity);
    }

    public function mergeEntities(Collection $entities): void
    {
        $this->gameState->entities = $this->gameState->entities->merge($entities);
    }

    public function getTurnPosition(): CellPosition
    {
        return $this->position;
    }

    public function hasCell(CellPosition $position): bool
    {
        return $this->gameState->board->hasCell($position);
    }

    public function getCell(CellPosition $position): ?Cell
    {
        return $this->gameState->board->getCell($position);
    }

    public function setCell(CellPosition $position, Cell $cell): void
    {
        $this->gameState->board->setCell($position, $cell);
    }

    public function mapCells(callable $callback): Collection
    {
        return $this->gameState->board->mapCells($callback);
    }

    public function mergeData(ContextData $contextData): self
    {
        $this->data = $this->data->merge($contextData);

        return $this;
    }

    public function getData(): ContextData
    {
        return $this->data;
    }

    public function applyCommand(Command $command): void
    {
        $command->execute($this);

        $this->appliedCommands->push($command);
    }

    public function getAppliedCommands(): Collection
    {
        return $this->appliedCommands;
    }

    public function finalizeTurn(): void
    {
        $this->gameState->finalizeTurn();
    }
}
