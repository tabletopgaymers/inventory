<?php

namespace App\Providers;

use App\Session\BaselineDatabaseSessionHandler;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make('session')->extend('database', function ($app) {
            return new BaselineDatabaseSessionHandler(
                $app->make('db')->connection($app['config']->get('session.connection')),
                $app['config']->get('session.table'),
                $app['config']->get('session.lifetime'),
                $app,
            );
        });
    }
}
