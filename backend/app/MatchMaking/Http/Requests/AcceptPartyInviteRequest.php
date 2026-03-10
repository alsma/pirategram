<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AcceptPartyInviteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'leaderId' => 'required|string',
        ];
    }

    public function leaderId(): int
    {
        $hashedId = $this->input('leaderId');
        $user = User::ofHashedId($hashedId)->first();

        if (!$user) {
            throw new \DomainException('Leader not found.');
        }

        return $user->id;
    }
}
