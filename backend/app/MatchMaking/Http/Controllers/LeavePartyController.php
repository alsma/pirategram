<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\LeavePartyRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\PartyManager;

class LeavePartyController
{
    public function __invoke(LeavePartyRequest $request, PartyManager $partyManager): MatchMakingOkResource
    {
        $user = $request->user();

        $partyManager->leave($user->id, $request->party());

        return MatchMakingOkResource::make(null);
    }
}
