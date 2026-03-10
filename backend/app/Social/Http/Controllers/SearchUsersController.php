<?php

declare(strict_types=1);

namespace App\Social\Http\Controllers;

use App\Social\FriendshipManager;
use App\Social\Http\Requests\SearchUsersRequest;
use App\Social\Http\Resources\UserSearchResource;

class SearchUsersController
{
    public function __invoke(SearchUsersRequest $request, FriendshipManager $friendshipManager): UserSearchResource
    {
        $user = $request->user();

        $results = $friendshipManager->searchUsers($request->searchQuery(), $user->id);

        return UserSearchResource::make($results);
    }
}
