<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\MatchMaking\PartyManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePartyInviteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'userId' => 'required|integer|exists:users,id',
            'mode' => ['required', 'string', Rule::in(array_keys(PartyManager::MODES))],
        ];
    }

    public function invitedUserId(): int
    {
        return (int) $this->input('userId');
    }

    public function mode(): string
    {
        return (string) $this->input('mode');
    }
}
