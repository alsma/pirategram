<?php

declare(strict_types=1);

use App\MatchMaking\Http\Controllers\AcceptPartyInviteController;
use App\MatchMaking\Http\Controllers\AcceptTicketController;
use App\MatchMaking\Http\Controllers\CancelPartySearchController;
use App\MatchMaking\Http\Controllers\CancelSearchController;
use App\MatchMaking\Http\Controllers\CreatePartyInviteController;
use App\MatchMaking\Http\Controllers\DeclinePartyInviteController;
use App\MatchMaking\Http\Controllers\DeclineTicketController;
use App\MatchMaking\Http\Controllers\GetStateController;
use App\MatchMaking\Http\Controllers\JoinPartyController;
use App\MatchMaking\Http\Controllers\KickPartyMemberController;
use App\MatchMaking\Http\Controllers\LeavePartyController;
use App\MatchMaking\Http\Controllers\PromotePartyMemberController;
use App\MatchMaking\Http\Controllers\StartPartySearchController;
use App\MatchMaking\Http\Controllers\StartSearchController;

Route::prefix('/mm')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/search/start', StartSearchController::class);
        Route::post('/search/cancel', CancelSearchController::class);
        Route::get('/state', GetStateController::class);
        Route::post('/ticket/{ticketId}/accept', AcceptTicketController::class);
        Route::post('/ticket/{ticketId}/decline', DeclineTicketController::class);

        // Party endpoints
        Route::post('/party/join', JoinPartyController::class);
        Route::post('/party/leave', LeavePartyController::class);
        Route::post('/party/invite', CreatePartyInviteController::class);
        Route::post('/party/invite/accept', AcceptPartyInviteController::class);
        Route::post('/party/invite/decline', DeclinePartyInviteController::class);
        Route::post('/party/kick', KickPartyMemberController::class);
        Route::post('/party/promote', PromotePartyMemberController::class);
        Route::post('/party/search/start', StartPartySearchController::class);
        Route::post('/party/search/cancel', CancelPartySearchController::class);
    });
