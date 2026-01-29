<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\KickPartyMemberRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\PartyManager;

class KickPartyMemberController
{
    public function __invoke(KickPartyMemberRequest $request, PartyManager $partyManager): MatchMakingOkResource
    {
        $user = $request->user();
        $party = $request->party();

        $partyManager->ensureIsLeader($party, $user->id);
        $partyManager->kick($user->id, $party, $request->memberUserId());

        return MatchMakingOkResource::make(null);
    }
}
