<?php

declare(strict_types=1);

namespace App\Auth\Http\Requests;

use App\Auth\Data\LoginDTO;
use Illuminate\Foundation\Http\FormRequest;

class Login extends FormRequest
{
    public function rules(): array
    {
        return [
            'identity' => 'required|string',
            'password' => 'required|string',
        ];
    }

    public function toDTO(): LoginDTO
    {
        return new LoginDTO(
            $this->str('identity')->toString(),
            $this->str('password')->toString(),
        );
    }
}
