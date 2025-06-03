<?php

declare(strict_types=1);

use App\Auth\Http\Controllers\LoginController;
use App\Auth\Http\Controllers\LogoutController;
use App\Auth\Http\Controllers\MeController;
use App\Auth\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('/auth')
    ->group(function (): void {
        Route::post('/register', RegisterController::class);
        Route::post('/login', LoginController::class);
        Route::post('/logout', LogoutController::class);

        Route::middleware('auth:sanctum')
            ->group(function (): void {
                Route::get('/me', MeController::class);
            });
    });
