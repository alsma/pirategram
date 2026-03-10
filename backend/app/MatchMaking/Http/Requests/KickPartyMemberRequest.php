<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\MatchMaking\Models\Party;
use App\MatchMaking\Models\PartyMember;
use App\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class KickPartyMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'memberUserId' => 'required|string',
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

    public function memberUserId(): int
    {
        $hashedId = $this->input('memberUserId');
        $user = User::ofHashedId($hashedId)->first();

        if (!$user) {
            throw new \DomainException('User not found.');
        }

        return $user->id;
    }
}
