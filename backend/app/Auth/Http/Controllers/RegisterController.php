<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use App\Auth\AuthManager;
use App\Auth\Http\Requests\Register;
use App\User\Http\Resources\MyUserResource;

class RegisterController
{
    public function __invoke(AuthManager $authManager, Register $request): MyUserResource
    {
        return MyUserResource::make($authManager->register($request->toDTO()));
    }
}
