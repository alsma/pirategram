<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\TicketActionRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\MatchMakingManager;

class DeclineTicketController
{
    public function __invoke(TicketActionRequest $request, MatchMakingManager $mm, string $ticketId): MatchMakingOkResource
    {
        $user = $request->user();

        $mm->declineTicket($user, $ticketId, $request->sessionId());

        return MatchMakingOkResource::make(null);
    }
}
