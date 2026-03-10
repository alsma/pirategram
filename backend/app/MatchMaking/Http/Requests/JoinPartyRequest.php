<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\MatchMaking\Models\Party;
use Illuminate\Foundation\Http\FormRequest;

class JoinPartyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'partyHash' => 'required|string',
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
}
