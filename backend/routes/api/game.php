<?php

declare(strict_types=1);

use App\Game\Http\Controllers\GetActiveGameController;
use App\Game\Http\Controllers\MakeTurnController;
use Illuminate\Support\Facades\Route;

Route::prefix('game')
    ->group(function (): void {
        Route::post('active-game', GetActiveGameController::class);
        Route::post('turn', MakeTurnController::class);
    });

Route::get('test', function (): void {
    phpinfo();
});
