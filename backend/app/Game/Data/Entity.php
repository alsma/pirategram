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
            $data['id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'game_player_id' => $this->gamePlayerId,
            'col' => $this->position->col,
            'row' => $this->position->row,
        ];
    }
}
