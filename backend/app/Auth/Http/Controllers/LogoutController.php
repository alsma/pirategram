<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use App\Auth\AuthManager;
use Illuminate\Http\JsonResponse;

class LogoutController
{
    public function __invoke(AuthManager $authManager): JsonResponse
    {
        $authManager->logout();

        return response()->json('ok');
    }
}
