<?php

declare(strict_types=1);

namespace App\Social\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'query' => 'required|string|min:2',
        ];
    }

    public function searchQuery(): string
    {
        return (string) $this->input('query');
    }
}
