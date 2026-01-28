<?php

declare(strict_types=1);

use App\MatchMaking\Http\Controllers\MatchMakingApiController;

Route::prefix('/mm')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/search/start', [MatchMakingApiController::class, 'startSearch']);
        Route::post('/search/cancel', [MatchMakingApiController::class, 'cancelSearch']);
        Route::get('/state', [MatchMakingApiController::class, 'getState']);
        Route::post('/ticket/{ticketId}/accept', [MatchMakingApiController::class, 'acceptTicket']);
        Route::post('/ticket/{ticketId}/decline', [MatchMakingApiController::class, 'declineTicket']);
    });
