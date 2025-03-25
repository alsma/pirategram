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
            'col' => $this->resource->cellPosition->col,
            'row' => $this->resource->cellPosition->row,
            'allowedWithCoin' => $this->resource->allowedWithCoins,
        ];
    }
}
