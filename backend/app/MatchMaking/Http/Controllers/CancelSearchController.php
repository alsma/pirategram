<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\CancelSearchRequest;
use App\MatchMaking\Http\Resources\MatchMakingOkResource;
use App\MatchMaking\MatchMakingManager;

class CancelSearchController
{
    public function __invoke(CancelSearchRequest $request, MatchMakingManager $mm): MatchMakingOkResource
    {
        $mm->cancelSearch($request->user(), $request->sessionId());

        return MatchMakingOkResource::make(null);
    }
}
