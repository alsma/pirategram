<?php

declare(strict_types=1);

namespace App\Social\Http\Controllers;

use App\Social\FriendshipManager;
use App\Social\Http\Resources\FriendsListResource;
use Illuminate\Http\Request;

class GetFriendsController
{
    public function __invoke(Request $request, FriendshipManager $friendshipManager): FriendsListResource
    {
        $user = $request->user();

        $friends = $friendshipManager->getFriends($user->id);

        return FriendsListResource::make($friends);
    }
}
