<?php

declare(strict_types=1);

namespace App\Auth\Http\Controllers;

use App\User\Http\Resources\MyUserResource;
use Illuminate\Http\Request;

class MeController
{
    public function __invoke(Request $request): MyUserResource
    {
        return MyUserResource::make($request->user());
    }
}
