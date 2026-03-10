<?php

declare(strict_types=1);

use App\MatchMaking\Models\Party;
use App\MatchMaking\Models\PartyMember;
use App\User\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{hash}', function (User $user, $userHash) {
    return $user->getHashedId() === $userHash;
});

Broadcast::channel('party.{partyHash}', function (User $user, string $partyHash) {
    $party = Party::ofHashedId($partyHash)->first();
    if (!$party) {
        return false;
    }

    return PartyMember::where('user_id', $user->id)
        ->where('party_id', $party->id)
        ->exists();
});
