<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Resources\MatchMakingStateResource;
use App\MatchMaking\MatchMakingManager;
use Illuminate\Http\Request;

class GetStateController
{
    public function __invoke(Request $request, MatchMakingManager $mm): MatchMakingStateResource
    {
        return MatchMakingStateResource::make($mm->getState($request->user()));
    }
}
