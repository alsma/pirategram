<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use App\MatchMaking\Data\MatchMakingTicketCreatedDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MatchMakingTicketCreatedDTO $resource */
class MatchMakingTicketCreatedResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'ticketId' => $this->resource->ticketId,
            'mode' => $this->resource->mode,
            'readyExpiresAt' => $this->resource->readyExpiresAt,
            'slotsTotal' => $this->resource->slotsTotal,
            'slots' => $this->resource->slots,
            'yourSlot' => $this->resource->yourSlot,
        ];
    }
}
