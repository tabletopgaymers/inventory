<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckApplicationSession
{
    public function handle(Request $request, Closure $next)
    {
        config(['session.expire_on_close' => true]);
        if (Auth::check()) {
            $user = Auth::user()->fresh();
            $timing = $request->session()->get('authentication');
            $now = now()->timestamp;
            $valid = $user && $user->enabled && is_array($timing)
                && is_int($timing['activity'] ?? null) && is_int($timing['started'] ?? null)
                && $timing['activity'] <= $now && $timing['started'] <= $now
                && ($timing['remember'] ?? false
                    ? $now < $timing['activity'] + 604800
                    : $now < $timing['activity'] + 1800 && $now < $timing['started'] + 43200);
            if (! $valid) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } else {
                Auth::setUser($user);
                // Browser navigation carries Sec-Fetch-User; form writes require CSRF.
                // Polling and automatic fetch requests never qualify as navigation.
                if ($request->header('Sec-Fetch-User') === '?1'
                    || (! $request->isMethodSafe() && ! $request->expectsJson() && $request->input('_deliberate') === '1')) {
                    $timing['activity'] = $now;
                    $request->session()->put('authentication', $timing);
                }
                config(['session.expire_on_close' => ! ($timing['remember'] ?? false)]);
            }
        }

        return $next($request);
    }
}
