<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\AcceptPartyInviteRequest;
use App\MatchMaking\Http\Resources\PartyResource;
use App\MatchMaking\PartyManager;

class AcceptPartyInviteController
{
    public function __invoke(AcceptPartyInviteRequest $request, PartyManager $partyManager): PartyResource
    {
        $user = $request->user();
        $party = $partyManager->acceptInvite($user->id, $request->leaderId());

        return PartyResource::make($party);
    }
}
