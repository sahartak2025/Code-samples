<?php

namespace App\Providers;

use App\Services\KrakenService;
use Illuminate\Support\ServiceProvider;

class KrakenServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('KrakenFacade', function () {
            return new KrakenService;
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
