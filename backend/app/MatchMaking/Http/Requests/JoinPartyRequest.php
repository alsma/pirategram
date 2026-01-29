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
            'partyId' => 'required|integer|exists:parties,id',
        ];
    }

    public function party(): Party
    {
        return Party::findOrFail($this->input('partyId'));
    }
}
