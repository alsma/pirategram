<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\MatchMakingManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MatchMakingController extends Controller
{
    public function start(Request $req, MatchMakingManager $matchMakingManager)
    {
        $req->validate([
            'party_id' => 'required|integer',
            'mode' => 'required|string|in:1v1,2v2,ffa4',
        ]);

        // Load party & members (from your DB); below is a sketch
        $party = $this->loadPartyForLeader($req->user()->id, (int) $req->input('party_id'), $req->string('mode')->toString());

        $matchMakingManager->startSearch($party);

        return response()->json(['ok' => true]);
    }

    public function cancel(Request $req, MatchMakingManager $matchMakingManager)
    {
        $req->validate([
            'party_id' => 'required|integer',
            'mode' => 'required|string|in:1v1,2v2,ffa4',
        ]);

        $matchMakingManager->cancelSearch((int) $req->input('party_id'), $req->string('mode')->toString());

        return response()->json(['ok' => true]);
    }

    public function accept(Request $req, MatchMakingManager $matchMakingManager, string $ticketId)
    {
        $matchMakingManager->acceptTicket($req->user()->id, $ticketId);

        return response()->json(['ok' => true]);
    }

    public function decline(Request $req, MatchMakingManager $matchMakingManager, string $ticketId)
    {
        $matchMakingManager->declineTicket($req->user()->id, $ticketId);

        return response()->json(['ok' => true]);
    }

    private function loadPartyForLeader(int $userId, int $partyId, string $mode): array
    {
        // TODO: fetch from your Party/PartyMember models.
        // Return array per startSearch contract:
        // ['party_id'=>..., 'leader_id'=>..., 'members'=>[['id'=>X,'mmr'=>Y],...], 'mode'=> $mode]
        // Enforce leader is the caller.
        throw_if(false, \Exception::class, 'Implement loadPartyForLeader');
    }
}
