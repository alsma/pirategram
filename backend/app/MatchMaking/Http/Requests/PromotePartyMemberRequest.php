<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\MatchMaking\Models\Party;
use Illuminate\Foundation\Http\FormRequest;

class PromotePartyMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'partyId' => 'required|integer|exists:parties,id',
            'userId' => 'required|integer|exists:users,id',
        ];
    }

    public function party(): Party
    {
        return Party::findOrFail($this->input('partyId'));
    }

    public function newLeaderUserId(): int
    {
        return (int) $this->input('userId');
    }
}
