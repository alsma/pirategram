<?php

declare(strict_types=1);

namespace App\Auth\Http\Requests;

use App\Auth\Data\RegisterDTO;
use Illuminate\Foundation\Http\FormRequest;

class Register extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|string|max:255|email|unique:users,email',
            'language' => 'nullable|string|max:3',
            'options' => 'nullable|array',
        ];
    }

    public function toDTO(): RegisterDTO
    {
        return new RegisterDTO(
            $this->str('email')->toString(),
            $this->str('language', '')->toString(),
            $this->array('options'),
        );
    }
}
