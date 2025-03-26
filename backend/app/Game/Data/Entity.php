<?php

declare(strict_types=1);

namespace App\Game\Data;

use Illuminate\Contracts\Support\Arrayable;

readonly class Entity implements Arrayable
{
    public string $id;

    public function __construct(
        public EntityType $type,
        public CellPosition $position,
        public ?int $gamePlayerId = null,
        public ?bool $isKilled = null,
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
            $data['is_killed'] ?? null,
            $data['id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'game_player_id' => $this->gamePlayerId,
            'is_killed' => $this->isKilled,
            'col' => $this->position->col,
            'row' => $this->position->row,
        ];
    }

    public function is(Entity $entity): bool
    {
        return $this->id === $entity->id;
    }

    public function updatePosition(CellPosition $position): self
    {
        return new self($this->type, $position, $this->gamePlayerId, $this->isKilled, $this->id);
    }

    public function kill(): self
    {
        return new self($this->type, $this->position, $this->gamePlayerId, true, $this->id);
    }
}
