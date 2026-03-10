<?php

declare(strict_types=1);

namespace App\Social\Http\Controllers;

use App\Social\FriendshipManager;
use App\Social\Http\Requests\SendFriendRequestRequest;
use App\Social\Http\Resources\FriendshipOkResource;

class SendFriendRequestController
{
    public function __invoke(SendFriendRequestRequest $request, FriendshipManager $friendshipManager): FriendshipOkResource
    {
        $user = $request->user();

        $friendshipManager->sendRequest($user->id, $request->friendId());

        return FriendshipOkResource::make(['ok' => true]);
    }
}
