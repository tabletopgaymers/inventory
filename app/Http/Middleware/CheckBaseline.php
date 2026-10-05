<?php

namespace App\Http\Middleware;

use App\Support\BaselineProbe;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

class CheckBaseline
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseline = app(BaselineProbe::class)->inspect();
        if (! $baseline['ready']) {
            return new \Illuminate\Http\Response(view('welcome', $baseline)->render(), 503);
        }
        $request->attributes->set('baseline', $baseline);

        try {
            return $next($request);
        } catch (QueryException|PDOException) {
            $baseline['ready'] = false;
            $baseline['message'] = 'Database session check failed. Run the documented local checks.';

            return new \Illuminate\Http\Response(view('welcome', $baseline)->render(), 503);
        }
    }
}
