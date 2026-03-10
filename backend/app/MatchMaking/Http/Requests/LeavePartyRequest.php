<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\MatchMaking\Models\Party;
use App\MatchMaking\Models\PartyMember;
use Illuminate\Foundation\Http\FormRequest;

class LeavePartyRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function party(): Party
    {
        $member = PartyMember::where('user_id', $this->user()->id)->first();

        if (!$member) {
            throw new \DomainException('Not in a party.');
        }

        return Party::findOrFail($member->party_id);
    }
}
