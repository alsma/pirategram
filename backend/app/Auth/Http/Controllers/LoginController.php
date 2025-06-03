<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use App\Auth\AuthManager;
use App\Auth\Http\Requests\Login;
use App\User\Http\Resources\MyUserResource;

class LoginController
{
    public function __invoke(AuthManager $authManager, Login $request): MyUserResource
    {
        return MyUserResource::make($authManager->login($request->toDTO()));
    }
}
