<?php

use App\Http\Middleware\CheckApplicationSession;
use App\Http\Middleware\CheckBaseline;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CheckBaseline::class);
        $middleware->trimStrings(except: ['first_name', 'last_name']);
        $middleware->web(append: [CheckApplicationSession::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, CheckApplicationSession::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Provider responses and SQL bindings may contain private data. Authentication
        // routes handle safe restart errors; database diagnostics stay out of logs.
        $exceptions->dontReport([QueryException::class]);
        $exceptions->report(function (PDOException $exception) {
            if (CheckBaseline::isSessionFailure($exception, request())) {
                return false;
            }
        });
        $exceptions->render(function (PDOException $exception, Request $request) {
            if (CheckBaseline::isSessionFailure($exception, $request)) {
                return CheckBaseline::sessionFailureResponse($request);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
