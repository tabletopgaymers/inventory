<?php

namespace App\Providers;

use App\Session\BaselineDatabaseSessionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider;

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
        Event::listen(
            SocialiteWasCalled::class,
            function (SocialiteWasCalled $event): void {
                $event->extendSocialite('microsoft', Provider::class);
            },
        );
        // Retain database rows long enough for rolling remembered authentication.
        // The application middleware enforces the shorter normal-session deadlines.
        config(['session.lifetime' => 10080]);
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
