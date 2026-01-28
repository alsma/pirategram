<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\StartSearchRequest;
use App\MatchMaking\Http\Resources\MatchMakingStartResource;
use App\MatchMaking\MatchMakingManager;

class StartSearchController
{
    public function __invoke(StartSearchRequest $request, MatchMakingManager $mm): MatchMakingStartResource
    {
        $user = $request->user();

        $result = $mm->startSearch($user, $request->mode(), $request->sessionId());

        return MatchMakingStartResource::make($result);
    }
}
