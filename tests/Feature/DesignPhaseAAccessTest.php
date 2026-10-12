<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DesignPhaseAAccessTest extends TestCase
{
    public function test_wrapper_is_get_only_authenticated_and_local_host_only(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $router = new Router($this->app['events'], $this->app);
        $this->app->instance('router', $router);
        Route::clearResolvedInstance('router');
        require base_path('routes/design-phase-a.php');

        $route = $router->getRoutes()->match(Request::create('https://tg-inventory-app.test/style-guide/wrapper'));
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('design.phase-a.local.wrapper', $route->getName());
        foreach (['dev-inventory.tabletopgaymers.org', 'unrelated.example'] as $host) {
            $this->assertCount(0, array_filter($router->getRoutes()->getRoutes(), fn ($candidate) => $candidate->uri() === 'style-guide/wrapper' && $candidate->getDomain() === $host));
        }
        $this->assertSame('design.phase-a.hosted.examples', $router->getRoutes()->match(Request::create('https://dev-inventory.tabletopgaymers.org/style-guide/examples'))->getName());
    }

    public function test_production_has_no_design_routes(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $router = new Router($this->app['events'], $this->app);
        $this->app->instance('router', $router);
        Route::clearResolvedInstance('router');
        require base_path('routes/design-phase-a.php');
        $this->assertCount(0, $router->getRoutes());
    }
}
