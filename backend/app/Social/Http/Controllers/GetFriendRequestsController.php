<?php

declare(strict_types=1);

namespace App\Social\Http\Controllers;

use App\Social\FriendshipManager;
use App\Social\Http\Resources\FriendRequestsResource;
use Illuminate\Http\Request;

class GetFriendRequestsController
{
    public function __invoke(Request $request, FriendshipManager $friendshipManager): FriendRequestsResource
    {
        $user = $request->user();

        $incoming = $friendshipManager->getIncomingRequests($user->id);
        $outgoing = $friendshipManager->getOutgoingRequests($user->id);

        return FriendRequestsResource::make([
            'incoming' => $incoming,
            'outgoing' => $outgoing,
        ]);
    }
}
