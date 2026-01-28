<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelSearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sessionId' => 'required|uuid',
        ];
    }

    public function sessionId(): string
    {
        return $this->input('sessionId');
    }
}
