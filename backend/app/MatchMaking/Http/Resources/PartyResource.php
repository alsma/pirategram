<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Resources;

use App\MatchMaking\Models\Party;
use App\MatchMaking\Models\PartyMember;
use App\MatchMaking\PartyManager;
use App\User\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Party $party */
        $party = $this->resource;

        $members = PartyMember::with('user:id,username')
            ->where('party_id', $party->id)
            ->get()
            ->map(fn ($m) => [
                'userId' => $m->user_id,
                'userHash' => $m->user->getHashedId(),
                'username' => $m->user->username,
            ])
            ->values()->all();

        return [
            'partyHash' => $party->getHashedId(),
            'leaderId' => $party->leader_id,
            'leaderHash' => User::find($party->leader_id)?->getHashedId(),
            'mode' => $party->mode,
            'members' => $members,
            'maxPlayers' => PartyManager::MODES[$party->mode] ?? 4,
        ];
    }
}
