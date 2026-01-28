<?php

declare(strict_types=1);

use App\MatchMaking\Http\Controllers\AcceptTicketController;
use App\MatchMaking\Http\Controllers\CancelSearchController;
use App\MatchMaking\Http\Controllers\DeclineTicketController;
use App\MatchMaking\Http\Controllers\GetStateController;
use App\MatchMaking\Http\Controllers\StartSearchController;

Route::prefix('/mm')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/search/start', StartSearchController::class);
        Route::post('/search/cancel', CancelSearchController::class);
        Route::get('/state', GetStateController::class);
        Route::post('/ticket/{ticketId}/accept', AcceptTicketController::class);
        Route::post('/ticket/{ticketId}/decline', DeclineTicketController::class);
    });
