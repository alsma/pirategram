<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use App\MatchMaking\Data\MatchMakingMatchStartingDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MatchMakingMatchStartingDTO $resource */
class MatchMakingMatchStartingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'ticketId' => $this->resource->ticketId,
            'startAt' => $this->resource->startAt,
        ];
    }
}
