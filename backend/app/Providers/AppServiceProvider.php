<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        JsonResource::withoutWrapping();
    }

    public function boot(): void
    {
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
