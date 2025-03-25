<?php

declare(strict_types=1);

namespace App\Game\Http\Resources;

use App\Game\Models\GamePlayer;
use App\User\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property GamePlayer $resource */
class GamePlayerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'hash' => $this->resource->getHashedId(),
            'user' => UserResource::make($this->resource->user),
            'order' => $this->resource->order,
            'teamId' => $this->resource->team_id,
        ];
    }
}
