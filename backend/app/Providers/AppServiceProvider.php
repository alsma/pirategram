<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Events\UserLoggedOut;
use App\MatchMaking\Events\MatchStarted;
use App\Social\Listeners\SetUserOfflineOnLogoutListener;
use App\Social\Listeners\SetUsersInGameOnMatchStartListener;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        JsonResource::withoutWrapping();
    }

    public function boot(): void
    {
        Event::listen(UserLoggedOut::class, SetUserOfflineOnLogoutListener::class);
        Event::listen(MatchStarted::class, SetUsersInGameOnMatchStartListener::class);
        $this->listenQueryDebugEvents();
    }

    public function listenQueryDebugEvents(): void
    {
        if (!($this->app->isLocal() && $this->app->make('config')->get('app.debug'))) {
            return;
        }

        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();
        Model::preventSilentlyDiscardingAttributes();
        $this->app->make('db')
            ->listen(function (QueryExecuted $query) {
                $this->app->make('log')
                    ->channel('db-queries')
                    ->debug("Time: {$query->time} Query: {$query->sql}", $query->bindings);
            });
    }
}
