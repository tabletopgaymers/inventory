<?php

namespace App\Http\Middleware;

use App\Support\BaselineProbe;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Session\DatabaseSessionHandler;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckBaseline
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseline = app(BaselineProbe::class)->inspect();
        if (! $baseline['ready']) {
            return new \Illuminate\Http\Response(view('welcome', $baseline)->render(), 503);
        }
        $request->attributes->set('baseline', $baseline);

        return $next($request);
    }

    public static function isSessionFailure(Throwable $exception, Request $request): bool
    {
        if (! ($exception instanceof QueryException || $exception instanceof PDOException)
            || ! ($request->attributes->get('baseline')['ready'] ?? false)) {
            return false;
        }

        // Laravel catches downstream failures before this global middleware returns.
        // Only database operations originating in its database session handler qualify.
        foreach ($exception->getTrace() as $frame) {
            if (isset($frame['class']) && is_a($frame['class'], DatabaseSessionHandler::class, true)) {
                return true;
            }
        }

        return false;
    }

    public static function sessionFailureResponse(Request $request): Response
    {
        $baseline = $request->attributes->get('baseline');
        $baseline['ready'] = false;
        $baseline['message'] = 'Database session check failed. Run the documented baseline checks.';

        return new \Illuminate\Http\Response(view('welcome', $baseline)->render(), 503);
    }
}
