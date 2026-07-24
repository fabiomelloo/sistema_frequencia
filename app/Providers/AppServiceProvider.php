<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $mysql = config('database.connections.mysql');

        if (is_array($mysql)) {
            config()->set('database.connections', ['mysql' => $mysql]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Password::defaults(fn (): Password => Password::min(12)->letters()->mixedCase()->numbers());
    }
}
