<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\DeclinePartyInviteRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\PartyManager;

class DeclinePartyInviteController
{
    public function __invoke(DeclinePartyInviteRequest $request, PartyManager $partyManager): MatchMakingOkResource
    {
        $user = $request->user();

        $partyManager->declineInvite($user->id, $request->leaderId());

        return MatchMakingOkResource::make(null);
    }
}
