<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\PartyManager;
use Illuminate\Http\Request;

class DisbandPartyController
{
    public function __invoke(Request $request, PartyManager $partyManager): MatchMakingOkResource
    {
        $user = $request->user();

        $party = $partyManager->getUserParty($user->id);

        if (!$party) {
            throw new \DomainException('You are not in a party.');
        }

        $partyManager->disband($user->id, $party);

        return MatchMakingOkResource::make(['ok' => true]);
    }
}
