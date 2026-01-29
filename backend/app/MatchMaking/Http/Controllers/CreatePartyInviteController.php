<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\CreatePartyInviteRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\PartyManager;

class CreatePartyInviteController
{
    public function __invoke(CreatePartyInviteRequest $request, PartyManager $partyManager): MatchMakingOkResource
    {
        $user = $request->user();
        $partyManager->createInvite($user->id, $request->invitedUserId(), $request->mode());

        return MatchMakingOkResource::make(null);
    }
}
