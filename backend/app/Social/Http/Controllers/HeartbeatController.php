<?php

declare(strict_types=1);

namespace App\Social\Http\Controllers;

use App\Social\FriendshipManager;
use App\Social\Http\Resources\FriendshipOkResource;
use Illuminate\Http\Request;

class HeartbeatController
{
    public function __invoke(Request $request, FriendshipManager $friendshipManager): FriendshipOkResource
    {
        $user = $request->user();

        $friendshipManager->heartbeat($user->id);

        return FriendshipOkResource::make(['ok' => true]);
    }
}
