<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\AcceptPartyInviteRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\PartyManager;

class AcceptPartyInviteController
{
    public function __invoke(AcceptPartyInviteRequest $request, PartyManager $partyManager): MatchMakingOkResource
    {
        $user = $request->user();

        $partyManager->acceptInvite($user->id, $request->leaderId());

        return MatchMakingOkResource::make(null);
    }
}
