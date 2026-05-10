<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Dusk\DuskServiceProvider as BaseDuskServiceProvider;

final class DuskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('production')) {
            return;
        }

        $this->app->register(BaseDuskServiceProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
