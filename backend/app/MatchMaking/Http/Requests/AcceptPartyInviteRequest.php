<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptPartyInviteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'leaderId' => 'required|integer|exists:users,id',
        ];
    }

    public function leaderId(): int
    {
        return (int) $this->input('leaderId');
    }
}
