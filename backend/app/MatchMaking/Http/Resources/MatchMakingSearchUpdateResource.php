<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use App\MatchMaking\Data\MatchMakingSearchUpdateDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MatchMakingSearchUpdateDTO $resource */
class MatchMakingSearchUpdateResource extends JsonResource
{
    public function toArray($request): array
    {
        return array_filter([
            'state' => $this->resource->state,
            'mode' => $this->resource->mode,
            'searchStartedAt' => $this->resource->searchStartedAt,
            'searchExpiresAt' => $this->resource->searchExpiresAt,
            'reason' => $this->resource->reason,
        ], fn ($value) => $value !== null);
    }
}
