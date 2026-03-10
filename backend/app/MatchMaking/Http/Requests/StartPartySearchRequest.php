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
            'partyHash' => 'required|string',
            'sessionId' => 'required|uuid',
        ];
    }

    public function party(): Party
    {
        $party = Party::ofHashedId($this->input('partyHash'))->first();

        if (!$party) {
            throw new \DomainException('Party not found.');
        }

        return $party;
    }

    public function sessionId(): string
    {
        return $this->input('sessionId');
    }
}
