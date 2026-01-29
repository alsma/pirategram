<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\StartPartySearchRequest;
use App\MatchMaking\Http\Resources\MatchMakingStartResource;
use App\MatchMaking\MatchMakingManager;
use App\MatchMaking\PartyManager;

class StartPartySearchController
{
    public function __invoke(
        StartPartySearchRequest $request,
        MatchMakingManager $mm,
        PartyManager $partyManager
    ): MatchMakingStartResource {
        $user = $request->user();
        $party = $request->party();

        $partyManager->ensureIsLeader($party, $user->id);

        $result = $mm->startPartySearch($party, $request->sessionId());

        return MatchMakingStartResource::make($result);
    }
}
