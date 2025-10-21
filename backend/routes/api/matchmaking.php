<?php

declare(strict_types=1);

use App\MatchMaking\Http\Controllers\MatchMakingController;

Route::prefix('/matchmaking')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/start', [MatchmakingController::class, 'start']);
        Route::post('/cancel', [MatchmakingController::class, 'cancel']);
        Route::post('/tickets/{ticketId}/accept', [MatchmakingController::class, 'accept']);
        Route::post('/tickets/{ticketId}/decline', [MatchmakingController::class, 'decline']);
    });
