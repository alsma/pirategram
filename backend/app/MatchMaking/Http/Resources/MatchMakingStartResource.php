<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use App\MatchMaking\Data\MatchMakingStartDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MatchMakingStartDTO $resource */
class MatchMakingStartResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'state' => $this->resource->state,
            'mode' => $this->resource->mode,
            'searchStartedAt' => $this->resource->searchStartedAt,
            'searchExpiresAt' => $this->resource->searchExpiresAt,
            'sessionId' => $this->resource->sessionId,
        ];
    }
}
