<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\JoinPartyRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\PartyManager;

class JoinPartyController
{
    public function __invoke(JoinPartyRequest $request, PartyManager $partyManager): MatchMakingOkResource
    {
        $user = $request->user();

        $partyManager->join($user->id, $request->party());

        return MatchMakingOkResource::make(null);
    }
}
