<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\MatchMaking\Models\Party;
use App\MatchMaking\Models\PartyMember;
use App\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class PromotePartyMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'newLeaderUserId' => 'required|string',
        ];
    }

    public function party(): Party
    {
        $member = PartyMember::where('user_id', $this->user()->id)->first();

        if (!$member) {
            throw new \DomainException('Not in a party.');
        }

        return Party::findOrFail($member->party_id);
    }

    public function newLeaderUserId(): int
    {
        $hashedId = $this->input('newLeaderUserId');
        $user = User::ofHashedId($hashedId)->first();

        if (!$user) {
            throw new \DomainException('User not found.');
        }

        return $user->id;
    }
}
