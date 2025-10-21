<?php

declare(strict_types=1);

namespace App\MatchMaking;

use App\MatchMaking\Commands\MatchMakingDaemon;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class MatchMakingServiceProvider extends ServiceProvider implements DeferrableProvider
{
    #[\Override]
    public function register(): void
    {
        $this->commands([
            MatchMakingDaemon::class,
        ]);

        $this->app->singleton(GroupAssembler::class);
        $this->app->singleton(MatchMakingManager::class, function () {
            return new MatchMakingManager(
                $this->app->make('redis'),
                $this->app->make(GroupAssembler::class),
            );
        });
    }

    #[\Override]
    public function provides(): array
    {
        return [
            MatchMakingDaemon::class,
            MatchMakingManager::class,
            GroupAssembler::class,
        ];
    }
}
