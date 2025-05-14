<?php

declare(strict_types=1);

namespace App\Game\Context;

use App\Game\Commands\Command;
use App\Game\Data\Cell;
use App\Game\Data\CellPosition;
use App\Game\Data\ContextData;
use App\Game\Data\Entity;
use App\Game\Data\EntityCollection;
use App\Game\Data\State;
use Illuminate\Support\Collection;

interface TurnContext
{
    public function getTurnPlayerId(): int;

    public function getTurnPlayerTeamId(): int;

    public function setTurnEntity(Entity $entity): void;

    public function getTurnEntity(): Entity;

    public function getTurnPosition(): CellPosition;

    /** @return Collection<int, int> */
    public function getTeammatePlayerIds(): Collection;

    public function hasCell(CellPosition $position): bool;

    public function getCell(CellPosition $position): ?Cell;

    public function setCell(CellPosition $position, Cell $cell): void;

    public function mapCells(callable $callback): Collection;

    public function getEntities(): EntityCollection;

    // Methods should not be called directly, allowed only for commands
    public function updateEntity(Entity $updatedEntity): void;

    public function removeEntity(Entity $entity): void;

    public function mergeEntities(Collection $entities): void;

    public function mergeData(ContextData $contextData): void;

    public function getData(): ContextData;

    public function getGameData(): State;

    public function updateGameData(State $data): void;

    public function applyCommand(Command $command): void;

    public function getAppliedCommands(): Collection;

    public function finalizeTurn(): void;
}
