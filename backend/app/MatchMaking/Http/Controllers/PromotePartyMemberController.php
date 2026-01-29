<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\PromotePartyMemberRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\PartyManager;

class PromotePartyMemberController
{
    public function __invoke(PromotePartyMemberRequest $request, PartyManager $partyManager): MatchMakingOkResource
    {
        $user = $request->user();
        $party = $request->party();

        $partyManager->ensureIsLeader($party, $user->id);
        $partyManager->promote($user->id, $party, $request->newLeaderUserId());

        return MatchMakingOkResource::make(null);
    }
}
