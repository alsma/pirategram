<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use App\MatchMaking\Data\MatchMakingTicketUpdatedDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MatchMakingTicketUpdatedDTO $resource */
class MatchMakingTicketUpdatedResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'ticketId' => $this->resource->ticketId,
            'updates' => $this->resource->updates,
            'acceptedCount' => $this->resource->acceptedCount,
            'declinedCount' => $this->resource->declinedCount,
        ];
    }
}
