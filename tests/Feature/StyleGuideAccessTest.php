<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

class StyleGuideAccessTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        if ($app->configurationIsCached() || (new Filesystem)->exists($app->getCachedRoutesPath())) {
            throw new \RuntimeException('Use an uncached disposable checkout for contributor checks.');
        }
        $app->loadEnvironmentFrom('.env.style-guide-access-test-nonexistent');
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set([
            'database.default' => 'style_guide_access_test_without_database',
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);

        return $app;
    }

    public function test_local_application_frame_and_wrapper_alias_are_get_only_and_require_authentication(): void
    {
        $routes = $this->styleGuideRouter('local')->getRoutes();
        $frame = $routes->match(Request::create('/style-guide/application-frame'));
        $alias = $routes->match(Request::create('/style-guide/wrapper'));

        $this->assertSame('style-guide.local.application-frame', $frame->getName());
        foreach ([$frame, $alias] as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_development_guide_requires_authentication_and_omits_application_frame(): void
    {
        $routes = $this->styleGuideRouter('development')->getRoutes();
        $examples = $routes->match(Request::create('/style-guide/examples'));

        $this->assertSame('style-guide.hosted.examples', $examples->getName());
        foreach ($routes as $route) {
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertNotSame('style-guide/application-frame', $route->uri());
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
        require base_path('routes/style-guide.php');

        return $router;
    }
}
