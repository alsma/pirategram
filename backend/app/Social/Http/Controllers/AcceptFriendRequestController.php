<?php

declare(strict_types=1);

namespace App\Social\Http\Controllers;

use App\Social\FriendshipManager;
use App\Social\Http\Requests\AcceptFriendRequestRequest;
use App\Social\Http\Resources\FriendshipOkResource;

class AcceptFriendRequestController
{
    public function __invoke(AcceptFriendRequestRequest $request, FriendshipManager $friendshipManager): FriendshipOkResource
    {
        $user = $request->user();

        $friendshipManager->acceptRequest($user->id, $request->requesterId());

        return FriendshipOkResource::make(['ok' => true]);
    }
}
