<?php

declare(strict_types=1);

namespace App\Social\Http\Requests;

use App\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RemoveFriendRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'friendHash' => 'required|string',
        ];
    }

    public function friendId(): int
    {
        $hashedId = $this->input('friendHash');
        $user = User::ofHashedId($hashedId)->first();

        if (!$user) {
            throw new \DomainException('User not found.');
        }

        return $user->id;
    }
}
