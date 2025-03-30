<?php

declare(strict_types=1);

namespace App\Game\Data;

use App\Exceptions\RuntimeException;
use App\Game\Support\HighOrderEntityStateUpdater;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @property-read HighOrderEntityStateUpdater $updateState
 */
readonly class Entity implements Arrayable
{
    public string $id;

    public function __construct(
        public EntityType $type,
        public CellPosition $position,
        public ?int $gamePlayerId = null,
        public EntityState $state = new EntityState,
        ?string $id = null,
    ) {
        $this->id = $id ?? $type->value.':'.str_random(6);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            EntityType::from($data['type']),
            new CellPosition($data['col'], $data['row']),
            $data['game_player_id'] ?? null,
            new EntityState($data['state'] ?? []),
            $data['id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'game_player_id' => $this->gamePlayerId,
            'state' => $this->state->toArray(),
            'col' => $this->position->col,
            'row' => $this->position->row,
        ];
    }

    public function is(Entity $entity): bool
    {
        return $this->id === $entity->id;
    }

    public function isNot(Entity $entity): bool
    {
        return !$this->is($entity);
    }

    public function updatePosition(CellPosition $position): self
    {
        return new self($this->type, $position, $this->gamePlayerId, $this->state, $this->id);
    }

    public function updateState(EntityState $state): self
    {
        return new self($this->type, $this->position, $this->gamePlayerId, $state, $this->id);
    }

    public function __get(string $key): mixed
    {
        if ($key === 'updateState') {
            return new HighOrderEntityStateUpdater($this);
        }

        throw new RuntimeException("Property [{$key}] does not exist on this instance.");
    }
}
