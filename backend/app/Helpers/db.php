<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

if (!function_exists('transaction')) {
    function transaction(callable $callback): mixed
    {
        return DB::transaction($callback, config('database.tx_attempts'));
    }
}

if (!function_exists('transaction_committed')) {
    function transaction_committed(callable $callback): void
    {
        if (app()->environment('testing')) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }
}
