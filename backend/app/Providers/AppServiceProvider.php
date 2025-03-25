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

        if ($this->app->isLocal()) {
            Model::preventLazyLoading();
            Model::preventAccessingMissingAttributes();
            Model::preventSilentlyDiscardingAttributes();
        }
    }

    public function boot(): void
    {
        if ($this->app->isLocal() && $this->app->make('config')->get('app.debug')) {
            $this->app->make('db')->listen(function (QueryExecuted $query) {
                logger()->channel('db-queries')->debug("Time: {$query->time} Query: {$query->sql}", $query->bindings);
            });
        }
    }
}
