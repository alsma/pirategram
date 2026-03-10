<?php

declare(strict_types=1);

use App\Social\Http\Controllers\AcceptFriendRequestController;
use App\Social\Http\Controllers\DeclineFriendRequestController;
use App\Social\Http\Controllers\GetFriendRequestsController;
use App\Social\Http\Controllers\GetFriendsController;
use App\Social\Http\Controllers\HeartbeatController;
use App\Social\Http\Controllers\SetAwayController;
use App\Social\Http\Controllers\RemoveFriendController;
use App\Social\Http\Controllers\SearchUsersController;
use App\Social\Http\Controllers\SendFriendRequestController;

Route::prefix('/friends')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', GetFriendsController::class);
        Route::get('/requests', GetFriendRequestsController::class);
        Route::get('/search', SearchUsersController::class);
        Route::post('/request', SendFriendRequestController::class);
        Route::post('/request/accept', AcceptFriendRequestController::class);
        Route::post('/request/decline', DeclineFriendRequestController::class);
        Route::post('/remove', RemoveFriendController::class);
        Route::post('/heartbeat', HeartbeatController::class);
        Route::post('/away', SetAwayController::class);
    });
