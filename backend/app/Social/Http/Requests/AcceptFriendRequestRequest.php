<?php

declare(strict_types=1);

namespace App\Social\Http\Requests;

use App\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AcceptFriendRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'requesterHash' => 'required|string',
        ];
    }

    public function requesterId(): int
    {
        $hashedId = $this->input('requesterHash');
        $user = User::ofHashedId($hashedId)->first();

        if (!$user) {
            throw new \DomainException('User not found.');
        }

        return $user->id;
    }
}
