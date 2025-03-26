<?php

declare(strict_types=1);

namespace App\Game\Http\Resources;

use App\Game\Data\Entity;
use App\Game\Models\GamePlayer;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Entity $resource */
class EntityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'isKilled' => $this->resource->isKilled,
            'col' => $this->resource->position->col,
            'row' => $this->resource->position->row,
            'playerHash' => $this->resource->gamePlayerId ? GamePlayer::keyToHashedId($this->resource->gamePlayerId) : null,
        ];
    }
}
