<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use App\MatchMaking\Data\MatchMakingIdleStateDTO;
use App\MatchMaking\Data\MatchMakingInMatchStateDTO;
use App\MatchMaking\Data\MatchMakingProposedStateDTO;
use App\MatchMaking\Data\MatchMakingSearchingStateDTO;
use App\MatchMaking\Data\MatchMakingStartingStateDTO;
use App\MatchMaking\Data\MatchMakingState;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MatchMakingState $resource */
class MatchMakingStateResource extends JsonResource
{
    public function toArray($request): array
    {
        $state = $this->resource;

        return match (true) {
            $state instanceof MatchMakingIdleStateDTO => [
                'state' => $state->state,
            ],
            $state instanceof MatchMakingSearchingStateDTO => [
                'state' => $state->state,
                'mode' => $state->mode,
                'searchStartedAt' => $state->searchStartedAt,
                'searchExpiresAt' => $state->searchExpiresAt,
            ],
            $state instanceof MatchMakingProposedStateDTO => [
                'state' => $state->state,
                'mode' => $state->mode,
                'ticketId' => $state->ticketId,
                'readyExpiresAt' => $state->readyExpiresAt,
                'slots' => $state->slots,
                'yourSlot' => $state->yourSlot,
            ],
            $state instanceof MatchMakingStartingStateDTO => [
                'state' => $state->state,
                'mode' => $state->mode,
                'ticketId' => $state->ticketId,
                'readyExpiresAt' => $state->readyExpiresAt,
                'slots' => $state->slots,
                'yourSlot' => $state->yourSlot,
                'startAt' => $state->startAt,
            ],
            $state instanceof MatchMakingInMatchStateDTO => [
                'state' => $state->state,
                'matchId' => $state->matchId,
            ],
            default => [],
        };
    }
}
