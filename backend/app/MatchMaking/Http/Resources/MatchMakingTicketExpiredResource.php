<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use App\MatchMaking\Data\MatchMakingTicketExpiredDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MatchMakingTicketExpiredDTO $resource */
class MatchMakingTicketExpiredResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'ticketId' => $this->resource->ticketId,
            'reason' => $this->resource->reason,
            'backToSearch' => $this->resource->backToSearch,
        ];
    }
}
