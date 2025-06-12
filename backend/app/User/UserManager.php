<?php

declare(strict_types=1);

namespace App\User;

use App\Exceptions\RuntimeException;
use App\User\Data\CreateUserDTO;
use App\User\Models\User;

class UserManager
{
    public function createUser(CreateUserDTO $data): User
    {
        return transaction(function () use ($data) {
            $emailTaken = User::lockForUpdate()->ofEmail($data->email)->exists();
            if ($emailTaken) {
                throw new RuntimeException('User with email already exists');
            }

            $usernameTaken = User::lockForUpdate()->ofUsername($data->username)->exists();
            if ($usernameTaken) {
                throw new RuntimeException('User with username already exists');
            }

            $user = new User;
            $user->username = $data->username;
            $user->email = $data->email;
            $user->password = $data->password;
            $user->language = $data->language;
            $user->save();

            return $user;
        });
    }
}
