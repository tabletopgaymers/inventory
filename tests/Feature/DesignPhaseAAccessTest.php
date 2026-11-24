<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

class DesignPhaseAAccessTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.style-guide-access-test-nonexistent');
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set([
            'database.default' => 'style_guide_access_test_without_database',
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);

        return $app;
    }

    public function test_local_wrapper_is_get_only_and_requires_authentication(): void
    {
        $router = $this->styleGuideRouter('local');
        $route = $router->getRoutes()->match(Request::create('/style-guide/wrapper'));

        $this->assertSame('design.phase-a.local.wrapper', $route->getName());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_development_guide_requires_authentication_and_omits_wrapper(): void
    {
        $routes = $this->styleGuideRouter('development')->getRoutes();
        $examples = $routes->match(Request::create('/style-guide/examples'));

        $this->assertSame('design.phase-a.hosted.examples', $examples->getName());
        foreach ($routes as $route) {
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertNotSame('style-guide/wrapper', $route->uri());
        }
    }

    public function test_production_has_no_style_guide_routes(): void
    {
        $this->assertCount(0, $this->styleGuideRouter('production')->getRoutes());
    }

    private function styleGuideRouter(string $environment): Router
    {
        $this->app->detectEnvironment(fn () => $environment);
        $router = new Router($this->app['events'], $this->app);
        $this->app->instance('router', $router);
        Route::clearResolvedInstance('router');
        require base_path('routes/design-phase-a.php');

        return $router;
    }
}
