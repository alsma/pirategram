<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\MatchMaking\Models\Party;
use Illuminate\Foundation\Http\FormRequest;

class StartPartySearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'partyId' => 'required|integer|exists:parties,id',
            'sessionId' => 'required|uuid',
        ];
    }

    public function party(): Party
    {
        return Party::findOrFail($this->input('partyId'));
    }

    public function sessionId(): string
    {
        return $this->input('sessionId');
    }
}
