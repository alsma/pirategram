<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Resources\PartyResource;
use App\MatchMaking\PartyManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetPartyController
{
    public function __invoke(Request $request, PartyManager $partyManager): PartyResource|JsonResponse
    {
        $user = $request->user();
        $party = $partyManager->getUserParty($user->id);

        if (!$party) {
            return response()->json(null);
        }

        return PartyResource::make($party);
    }
}
