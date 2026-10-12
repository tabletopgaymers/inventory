<?php

namespace App\Http\Controllers;

use App\Exceptions\DisabledAccountException;
use App\Support\MicrosoftConfiguration;
use App\Support\MicrosoftIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class MicrosoftAuthController extends Controller
{
    private function provider()
    {
        if (! app(MicrosoftConfiguration::class)->ready()) {
            throw new \RuntimeException('Development Microsoft configuration incomplete.');
        }

        return Socialite::driver('microsoft')->setScopes(config('microsoft_auth.scopes'))->enablePKCE();
    }

    public function redirect(Request $request)
    {
        if (Auth::check()) {
            return redirect($this->landingDestination($request));
        }
        try {
            $request->session()->put('microsoft_attempt', ['started' => now()->timestamp, 'remember' => $request->boolean('remember')]);

            return $this->provider()->with(['prompt' => 'select_account'])->redirect();
        } catch (Throwable) {
            return $this->failure($request);
        }
    }

    public function callback(Request $request)
    {
        $attempt = $request->session()->pull('microsoft_attempt');
        $stage = 'pending attempt';
        try {
            if (! is_array($attempt) || ! is_int($attempt['started'] ?? null)
                || now()->timestamp >= $attempt['started'] + 300 || now()->timestamp < $attempt['started']
                || $request->has('error') || Auth::check()) {
                throw new \RuntimeException;
            }
            $stage = 'provider configuration';
            $provider = $this->provider();
            $stage = 'provider exchange and claims';
            $external = $provider->user();
            $claims = $provider->getClaims();
            if (! is_object($claims)) {
                throw new \RuntimeException;
            }
            $stage = 'local identity';
            $user = app(MicrosoftIdentity::class)->resolve($external, $claims);
            $stage = 'local session';
            Auth::login($user, false);
            $request->session()->regenerate();
            $request->session()->put('authentication', [
                'started' => now()->timestamp, 'activity' => now()->timestamp,
                'remember' => ($attempt['remember'] ?? false) === true,
            ]);
            config(['session.expire_on_close' => ! (($attempt['remember'] ?? false) === true)]);

            return redirect($this->landingDestination($request));
        } catch (DisabledAccountException) {
            return $this->failure($request, 'This account has been disabled. Contact your administrator.');
        } catch (Throwable) {
            Log::warning('Microsoft sign-in failed.', ['stage' => $stage]);

            return $this->failure($request);
        }
    }

    private function landingDestination(Request $request): string
    {
        $destination = $request->session()->pull('url.intended');
        if (! is_string($destination)) {
            return '/profile';
        }
        // Laravel remembers a full URL. Keep sign-in redirects on this application.
        $origin = $request->getSchemeAndHttpHost();
        if (str_starts_with($destination, $origin.'/')) {
            $destination = substr($destination, strlen($origin));
        }
        if (! str_starts_with($destination, '/') || str_starts_with($destination, '//')
            || str_contains($destination, '\\') || preg_match('/[\x00-\x20]/', $destination)) {
            return '/profile';
        }
        $path = parse_url($destination, PHP_URL_PATH);
        if (! is_string($path) || $path === '/' || $path === '/login' || $path === '/signed-out'
            || str_starts_with($path, '/auth/')) {
            return '/profile';
        }

        return $destination;
    }

    private function failure(Request $request, string $message = 'Sign-in could not be completed. Please start again.')
    {
        $request->session()->forget(['microsoft_attempt', 'state', 'code_verifier']);

        return redirect('/login')->with('error', $message);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/signed-out')->with('site_logout', true);
    }

    public function microsoftLogout(Request $request)
    {
        try {
            $url = $this->provider()->getLogoutUrl(config('microsoft_auth.post_logout_redirect_uri'));
            $request->session()->put('microsoft_logout_requested', true);

            return redirect()->away($url);
        } catch (Throwable) {
            return redirect('/signed-out')->with('error', 'Microsoft sign-out could not be started. Please try again.');
        }
    }
}
