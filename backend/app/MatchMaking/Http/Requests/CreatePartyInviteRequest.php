<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\MatchMaking\PartyManager;
use App\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePartyInviteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'userId' => 'required|string',
            'mode' => ['required', 'string', Rule::in(array_keys(PartyManager::MODES))],
        ];
    }

    public function invitedUserId(): int
    {
        $hashedId = $this->input('userId');
        $user = User::ofHashedId($hashedId)->first();

        if (!$user) {
            throw new \DomainException('User not found.');
        }

        return $user->id;
    }

    public function mode(): string
    {
        return (string) $this->input('mode');
    }
}
