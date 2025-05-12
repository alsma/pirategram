<?php

declare(strict_types=1);

namespace App\Game\Http\Resources;

use App\Game\Data\EntityTurn;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property EntityTurn $resource */
class EntityTurnResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'entityId' => $this->resource->entityId,
            'col' => $this->resource->position->col,
            'row' => $this->resource->position->row,
            'canCarry' => $this->resource->canCarry,
        ];
    }
}
