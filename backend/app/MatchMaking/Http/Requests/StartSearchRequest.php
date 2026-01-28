<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Requests;

use App\MatchMaking\ValueObjects\GameMode;
use Illuminate\Foundation\Http\FormRequest;

class StartSearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mode' => 'required|string|in:1v1,2v2,ffa4',
            'sessionId' => 'required|uuid',
        ];
    }

    public function mode(): GameMode
    {
        return GameMode::from($this->input('mode'));
    }

    public function sessionId(): string
    {
        return $this->input('sessionId');
    }
}
