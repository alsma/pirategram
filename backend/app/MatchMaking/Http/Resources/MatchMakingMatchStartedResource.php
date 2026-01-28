<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use App\MatchMaking\Data\MatchMakingMatchStartedDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MatchMakingMatchStartedDTO $resource */
class MatchMakingMatchStartedResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'matchId' => $this->resource->matchId,
        ];
    }
}
