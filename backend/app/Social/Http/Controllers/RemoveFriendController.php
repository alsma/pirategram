<?php

declare(strict_types=1);

namespace App\Social\Http\Controllers;

use App\Social\FriendshipManager;
use App\Social\Http\Requests\RemoveFriendRequest;
use App\Social\Http\Resources\FriendshipOkResource;

class RemoveFriendController
{
    public function __invoke(RemoveFriendRequest $request, FriendshipManager $friendshipManager): FriendshipOkResource
    {
        $user = $request->user();

        $friendshipManager->removeFriend($user->id, $request->friendId());

        return FriendshipOkResource::make(['ok' => true]);
    }
}
