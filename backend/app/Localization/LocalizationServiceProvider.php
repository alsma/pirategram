<?php

declare(strict_types=1);

namespace App\Localization;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class LocalizationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(LocalizationManager::class);
        $this->app->when(LocalizationManager::class)
            ->needs('$config')
            ->give(fn () => config('localization'));
    }

    public function provides(): array
    {
        return [LocalizationManager::class];
    }
}
