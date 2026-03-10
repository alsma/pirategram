<?php

declare(strict_types=1);

namespace App\Social\Http\Controllers;

use App\Social\FriendshipManager;
use App\Social\Http\Requests\DeclineFriendRequestRequest;
use App\Social\Http\Resources\FriendshipOkResource;

class DeclineFriendRequestController
{
    public function __invoke(DeclineFriendRequestRequest $request, FriendshipManager $friendshipManager): FriendshipOkResource
    {
        $user = $request->user();

        $friendshipManager->declineRequest($user->id, $request->requesterId());

        return FriendshipOkResource::make(['ok' => true]);
    }
}
