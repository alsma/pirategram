<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\CancelSearchRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\MatchMakingManager;
use App\MatchMaking\PartyManager;

class CancelPartySearchController
{
    public function __invoke(
        CancelSearchRequest $request,
        MatchMakingManager $mm,
        PartyManager $partyManager
    ): MatchMakingOkResource {
        $user = $request->user();

        $party = $partyManager->getUserParty($user->id);

        if (!$party) {
            throw new \DomainException('Not in a party');
        }

        $partyManager->ensureIsLeader($party, $user->id);

        $mm->cancelPartySearch($party, $request->sessionId());

        return MatchMakingOkResource::make(null);
    }
}
