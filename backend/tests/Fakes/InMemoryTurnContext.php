<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Game\Commands\Command;
use App\Game\Context\TurnContext;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\ContextData;
use App\Game\Data\Entity;
use App\Game\Data\EntityCollection;
use App\Game\Data\EntityType;
use App\Game\Data\GameBoard;
use Illuminate\Support\Collection;

class InMemoryTurnContext implements TurnContext
{
    private bool $turnFinalized = false;

    private Collection $appliedCommands;

    public function __construct(
        public readonly GameBoard $gameBoard,
        public readonly int $turnPlayerId,
        public Entity $turnEntity,
        public readonly CellPosition $turnPosition,
        public readonly Collection $teammatePlayerIds,
        public EntityCollection $entities,
        public ?ContextData $data = null,
    ) {
        $this->appliedCommands = collect();
        $this->data = $this->data ?? new ContextData([]);
    }

    public static function createSimpleContext(GameBoard $gameBoard, int $turnPlayerId, EntityCollection $entities): self
    {
        return new self(
            $gameBoard,
            $turnPlayerId,
            new Entity(EntityType::Null, new CellPosition(0, 0)),
            new CellPosition(0, 0),
            collect([$turnPlayerId]),
            $entities,
        );
    }

    public function getTurnPlayerId(): int
    {
        return $this->turnPlayerId;
    }

    public function setTurnEntity(Entity $entity): TurnContext
    {
        $this->turnEntity = $entity;

        return $this;
    }

    public function getTurnEntity(): Entity
    {
        return $this->entities->getEntityByIdOrFail($this->turnEntity->id);
    }

    public function getTurnPosition(): CellPosition
    {
        return $this->turnPosition;
    }

    public function getTeammatePlayerIds(): Collection
    {
        return $this->teammatePlayerIds->mapWithKeys(fn (int $playerId) => [$playerId => $playerId]);
    }

    public function hasCell(CellPosition $position): bool
    {
        return $this->gameBoard->hasCell($position);
    }

    public function getCell(CellPosition $position): ?Cell
    {
        return $this->gameBoard->getCell($position);
    }

    public function setCell(CellPosition $position, Cell $cell): void
    {
        $this->gameBoard->setCell($position, $cell);
    }

    public function mapCells(callable $callback): Collection
    {
        return $this->gameBoard->mapCells($callback);
    }

    public function getEntities(): EntityCollection
    {
        return $this->entities;
    }

    public function updateEntity(Entity $updatedEntity): void
    {
        $this->entities = $this->entities->updateEntity($updatedEntity);
    }

    public function mergeEntities(Collection $entities): void
    {
        $this->entities = $this->entities->merge($entities);
    }

    public function mergeData(ContextData $contextData): TurnContext
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
        $this->turnFinalized = true;
    }

    public function isTurnFinalized(): bool
    {
        return $this->turnFinalized;
    }
}
