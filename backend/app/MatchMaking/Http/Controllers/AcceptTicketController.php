<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\TicketActionRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\MatchMakingManager;

class AcceptTicketController
{
    public function __invoke(TicketActionRequest $request, MatchMakingManager $mm, string $ticketId): MatchMakingOkResource
    {
        $mm->acceptTicket($request->user(), $ticketId, $request->sessionId());

        return MatchMakingOkResource::make(null);
    }
}
