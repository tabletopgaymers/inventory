<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckBaseline;
use App\Models\User;
use App\Support\AccessManagement;
use App\Support\BaselineProbe;
use App\Support\MicrosoftConfiguration;
use App\Support\MicrosoftIdentity;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    // Synthetic identity keys only; no sample people or live tenant identities.
    private const TENANT = '11111111-1111-1111-1111-111111111111';

    private const OBJECT = '22222222-2222-2222-2222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->assertTrue(app(BaselineProbe::class)->inspect()['ready']);
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['authenticationReady']);
        DB::beginTransaction();
        config([
            'app.url' => 'https://tg-inventory-app.test',
            'services.microsoft.tenant' => self::TENANT,
            'services.microsoft.client_id' => '33333333-3333-3333-3333-333333333333',
            'services.microsoft.client_secret' => 'synthetic-unit-test-secret',
            'services.microsoft.redirect' => 'https://tg-inventory-app.test/auth/microsoft/callback',
            'microsoft_auth.post_logout_redirect_uri' => 'https://tg-inventory-app.test/signed-out',
            'microsoft_auth.bootstrap_tenant' => self::TENANT,
            'microsoft_auth.bootstrap_object' => self::OBJECT,
        ]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $this->travelBack();
        parent::tearDown();
    }

    private function user(array $roles = ['basic'], bool $attested = false): User
    {
        $user = User::create(['contact_email' => $attested ? 'synthetic-test@tabletopgaymers.org' : null, 'contact_attested' => $attested]);
        foreach ($roles as $role) {
            DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => $role]);
        }

        return $user;
    }

    private function signedIn(User $user, bool $remember = false, ?int $started = null, ?int $activity = null): static
    {
        return $this->actingAs($user)->withSession(['authentication' => [
            'started' => $started ?? now()->timestamp, 'activity' => $activity ?? now()->timestamp, 'remember' => $remember,
        ]]);
    }

    private function external(string $object = self::OBJECT)
    {
        return (new \SocialiteProviders\Manager\OAuth2\User)->setRaw(['givenName' => '', 'surname' => ''])
            ->map(['id' => $object, 'email' => null]);
    }

    public function test_first_and_repeated_provisioning_preserve_names_roles_and_contact(): void
    {
        $claims = (object) ['tid' => self::TENANT, 'oid' => self::OBJECT];
        $identity = app(MicrosoftIdentity::class);
        $user = $identity->resolve($this->external(), $claims);
        $this->assertSame(['basic'], $user->roles());
        $user->contact_email = 'synthetic-test@tabletopgaymers.org';
        $user->contact_attested = true;
        $user->save();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'manager']);
        $again = $identity->resolve($this->external(), $claims);
        $this->assertSame($user->id, $again->id);
        $this->assertSame('', $again->first_name);
        $this->assertSame(['basic', 'manager'], $again->roles());
        $this->assertTrue($again->contact_attested);
        $this->assertSame(1, DB::table('external_identities')->where('object_id', self::OBJECT)->count());
    }

    public function test_same_email_does_not_link_different_identity(): void
    {
        $first = app(MicrosoftIdentity::class)->resolve($this->external(), (object) ['tid' => self::TENANT, 'oid' => self::OBJECT]);
        $object = '44444444-4444-4444-4444-444444444444';
        $second = app(MicrosoftIdentity::class)->resolve($this->external($object), (object) ['tid' => self::TENANT, 'oid' => $object]);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_two_concurrent_first_logins_create_one_identity_and_user(): void
    {
        DB::rollBack();
        $object = (string) Str::uuid();
        $processes = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/provisioning-worker.php'), $object], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Isolated worker must finish successfully.');
            }
            $this->assertSame($processes[0]->getOutput(), $processes[1]->getOutput());
            $this->assertSame(1, DB::table('external_identities')->where('tenant_id', self::TENANT)->where('object_id', $object)->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            // Remove only the random synthetic identity created by this fixture.
            $ids = DB::table('external_identities')->where('tenant_id', self::TENANT)->where('object_id', $object)->pluck('user_id');
            DB::table('external_identities')->where('tenant_id', self::TENANT)->where('object_id', $object)->delete();
            DB::table('user_roles')->whereIn('user_id', $ids)->delete();
            DB::table('users')->whereIn('id', $ids)->delete();
            DB::beginTransaction();
        }
    }

    public function test_wrong_tenant_or_inconsistent_object_is_rejected_without_provisioning(): void
    {
        $before = User::count();
        foreach ([(object) ['tid' => self::OBJECT, 'oid' => self::OBJECT], (object) ['tid' => self::TENANT, 'oid' => self::TENANT], (object) []] as $claims) {
            try {
                app(MicrosoftIdentity::class)->resolve($this->external(), $claims);
                $this->fail('Invalid identity accepted');
            } catch (\RuntimeException) {
                $this->assertSame($before, User::count());
            }
        }
    }

    public function test_callback_uses_same_provider_claims_and_regenerates_session(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('setScopes')->once()->with(['openid', 'profile', 'email', 'User.Read'])->andReturnSelf();
        $provider->shouldReceive('enablePKCE')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($this->external());
        $provider->shouldReceive('getClaims')->once()->andReturn((object) ['tid' => self::TENANT, 'oid' => self::OBJECT]);
        Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($provider);
        $this->withSession(['microsoft_attempt' => ['started' => now()->timestamp, 'remember' => true]]);
        $old = session()->getId();
        $this->get('/auth/microsoft/callback?code=synthetic-code')->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertNotSame($old, session()->getId());
        $this->assertTrue(session('authentication.remember'));
        $this->assertNull(session('microsoft_attempt'));
    }

    public function test_cancel_stale_and_replayed_callbacks_do_not_call_provider(): void
    {
        Socialite::shouldReceive('driver')->never();
        $before = User::count();
        $this->withSession(['microsoft_attempt' => ['started' => now()->timestamp - 300, 'remember' => false]])
            ->get('/auth/microsoft/callback?code=synthetic-code')->assertRedirect('/login');
        $this->get('/auth/microsoft/callback?code=synthetic-code')->assertRedirect('/login');
        $this->withSession(['microsoft_attempt' => ['started' => now()->timestamp, 'remember' => false]])
            ->get('/auth/microsoft/callback?error=access_denied')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame($before, User::count());
    }

    public function test_state_or_provider_failure_has_safe_restart_and_no_login(): void
    {
        Log::spy();
        $provider = Mockery::mock();
        $provider->shouldReceive('setScopes')->andReturnSelf();
        $provider->shouldReceive('enablePKCE')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new InvalidStateException('synthetic-sensitive-body'));
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);
        $this->withSession(['microsoft_attempt' => ['started' => now()->timestamp, 'remember' => false]])
            ->get('/auth/microsoft/callback')->assertRedirect('/login');
        $this->get('/login')->assertDontSee('synthetic-sensitive-body')->assertSee('Please start again');
        $this->assertGuest();
        Log::shouldHaveReceived('warning')->once()->with('Microsoft sign-in failed.', ['stage' => 'provider exchange and claims']);
    }

    public function test_actual_adapter_redirect_requests_account_selection_with_fixed_tenant_scopes_state_and_pkce(): void
    {
        foreach ([false, true] as $remember) {
            $response = $this->get('/auth/microsoft/redirect?remember='.(int) $remember)->assertRedirect();
            $url = $response->headers->get('Location');
            $this->assertStringContainsString('/'.self::TENANT.'/oauth2/v2.0/authorize', $url);
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame('select_account', $query['prompt']);
            $this->assertSame('https://tg-inventory-app.test/auth/microsoft/callback', $query['redirect_uri']);
            $scopes = explode(' ', $query['scope']);
            sort($scopes);
            $expected = ['openid', 'profile', 'email', 'User.Read'];
            sort($expected);
            $this->assertSame($expected, $scopes);
            $this->assertSame('S256', $query['code_challenge_method']);
            $this->assertNotEmpty($query['state']);
            $this->assertSame(session('state'), $query['state']);
            $verifier = session('code_verifier');
            $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), $query['code_challenge']);
            $this->assertSame($remember, session('microsoft_attempt.remember'));
        }
    }

    public function test_existing_authenticated_session_does_not_start_account_selection(): void
    {
        Socialite::shouldReceive('driver')->never();
        $user = $this->user();
        $this->signedIn($user)->get('/auth/microsoft/redirect')->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('microsoft_attempt'));
    }

    public function test_configuration_rejects_wrong_origins_tenants_scopes_and_missing_bootstrap(): void
    {
        $this->assertTrue(app(MicrosoftConfiguration::class)->ready());
        foreach (['services.microsoft.tenant' => 'common', 'services.microsoft.redirect' => 'https://unapproved.test/callback',
            'microsoft_auth.bootstrap_object' => '', 'microsoft_auth.scopes' => ['offline_access']] as $key => $bad) {
            $old = config($key);
            config([$key => $bad]);
            $this->assertFalse(app(MicrosoftConfiguration::class)->ready());
            config([$key => $old]);
        }
    }

    public function test_actual_adapter_sends_matching_pkce_verifier_at_token_exchange(): void
    {
        $this->get('/auth/microsoft/redirect')->assertRedirect();
        $verifier = session('code_verifier');
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"access_token":"synthetic-token"}'),
        ]));
        $stack->push(Middleware::history($history));
        $provider = Socialite::driver('microsoft')->enablePKCE();
        $provider->setHttpClient(new Client(['handler' => $stack]));
        $provider->getAccessTokenResponse('synthetic-code');
        parse_str((string) $history[0]['request']->getBody(), $body);
        $this->assertSame($verifier, $body['code_verifier']);
        $this->assertSame('synthetic-code', $body['code']);
        $this->assertSame('https://tg-inventory-app.test/auth/microsoft/callback', $body['redirect_uri']);
        $this->assertNull(session('code_verifier'));
    }

    public function test_actual_adapter_rejects_incorrect_state_before_exchange(): void
    {
        $this->get('/auth/microsoft/redirect')->assertRedirect();
        $this->get('/auth/microsoft/callback?state=wrong&code=synthetic-code')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNull(session('state'));
    }

    public function test_profile_tampering_cannot_change_roles_identity_email_or_enabled(): void
    {
        $user = $this->user();
        $this->signedIn($user)->post('/profile', ['first_name' => '', 'last_name' => '', 'enabled' => false,
            'roles' => ['admin'], 'provider_email' => 'tampered', 'contact_email' => 'synthetic-test@tabletopgaymers.org', 'contact_attested' => 1])->assertRedirect('/profile');
        $this->assertTrue($user->fresh()->enabled);
        $this->assertNull($user->fresh()->provider_email);
        $this->assertSame(['basic'], $user->roles());
    }

    public function test_contact_change_clears_old_attestation_and_allows_explicit_new_confirmation(): void
    {
        $user = $this->user(['basic', 'manager'], true);
        $this->signedIn($user)->post('/profile', ['first_name' => '', 'last_name' => '', 'contact_email' => 'synthetic-changed@tabletopgaymers.org'])->assertRedirect('/profile');
        $this->assertFalse($user->fresh()->contact_attested);
        $this->assertSame(['basic', 'manager'], $user->roles());
        $this->post('/profile', ['first_name' => '', 'last_name' => '', 'contact_email' => 'synthetic-confirmed@tabletopgaymers.org', 'contact_attested' => 1])->assertRedirect('/profile');
        $this->assertTrue($user->fresh()->contact_attested);
    }

    public function test_name_unicode_limit_control_characters_and_contact_domain_are_validated(): void
    {
        $user = $this->user();
        $this->signedIn($user)->post('/profile', ['first_name' => str_repeat('界', 101)])->assertSessionHasErrors('first_name');
        $this->post('/profile', ['first_name' => "x\x01"])->assertSessionHasErrors('first_name');
        $this->post('/profile', ['contact_email' => 'synthetic@sub.tabletopgaymers.org'])->assertSessionHasErrors('contact_email');
        $this->post('/profile', ['contact_email' => ['malformed']])->assertSessionHasErrors('contact_email');
        $this->post('/profile', ['first_name' => "\n"])->assertSessionHasErrors('first_name');
        $this->post('/profile', ['first_name' => '  '.str_repeat('界', 100).'  '])->assertRedirect('/profile');
        $this->assertSame(str_repeat('界', 100), $user->fresh()->first_name);
    }

    public function test_role_authority_contact_gate_and_unrelated_roles(): void
    {
        $actor = $this->user(['basic', 'manager', 'procurement'], true);
        $target = $this->user(['basic']);
        $this->signedIn($actor)->post('/users/'.$target->id.'/roles', ['role' => 'manager', 'grant' => 1])->assertSessionHasErrors('role');
        $this->assertSame(['basic'], $target->roles());
        $this->post('/users/'.$target->id.'/roles', ['role' => 'admin', 'grant' => 1])->assertForbidden();
        $target->contact_email = 'synthetic-target@tabletopgaymers.org';
        $target->contact_attested = true;
        $target->save();
        $this->post('/users/'.$target->id.'/roles', ['role' => 'manager', 'grant' => 1])->assertRedirect('/users');
        $this->post('/users/'.$target->id.'/roles', ['role' => 'procurement', 'grant' => 1])->assertRedirect('/users');
        $this->post('/users/'.$target->id.'/roles', ['role' => 'manager', 'grant' => 0])->assertRedirect('/users');
        $this->assertSame(['basic', 'procurement'], $target->roles());
        $this->assertSame(3, DB::table('access_audits')->where('target_id', $target->id)->count());
    }

    public function test_bootstrap_grants_admin_once_after_self_confirmation_and_never_regrants(): void
    {
        $user = app(MicrosoftIdentity::class)->resolve($this->external(), (object) ['tid' => self::TENANT, 'oid' => self::OBJECT]);
        $this->assertSame(['basic'], $user->roles());
        $this->signedIn($user)->post('/profile', ['contact_email' => 'synthetic-bootstrap@tabletopgaymers.org', 'contact_attested' => 1])->assertRedirect('/profile');
        $this->assertTrue($user->hasRole('admin'));
        $this->assertNotNull(DB::table('authentication_bootstraps')->where('key', 'initial-admin')->value('consumed_at'));
        $this->post('/users/'.$user->id.'/roles', ['role' => 'admin', 'grant' => 0])->assertRedirect('/users');
        $this->post('/profile', ['contact_email' => 'synthetic-bootstrap@tabletopgaymers.org', 'contact_attested' => 1])->assertRedirect('/profile');
        $this->assertFalse($user->hasRole('admin'));
        $this->assertSame(1, DB::table('access_audits')->where('action', 'initial-admin')->count());
    }

    public function test_disable_and_role_removal_take_effect_on_next_remembered_request(): void
    {
        $admin = $this->user(['basic', 'admin'], true);
        $target = $this->user(['basic', 'manager'], true);
        app(AccessManagement::class)->changeRole($admin->id, $target->id, 'manager', false);
        $this->signedIn($target, true)->post('/users/'.$admin->id.'/roles', ['role' => 'manager', 'grant' => 0])->assertForbidden();
        $this->get('/users')->assertOk();
        app(AccessManagement::class)->disable($admin->id, $target->id);
        $this->get('/users')->assertRedirect('/login');
        $this->assertGuest();
        $this->signedIn($admin)->post('/users/'.$target->id.'/disable')->assertRedirect('/users');
    }

    public function test_audit_failure_rolls_back_role_change_and_disablement(): void
    {
        $admin = $this->user(['basic', 'admin'], true);
        $target = $this->user(['basic'], true);
        // A query listener throws immediately after audit insertion inside the transaction.
        Event::listen(QueryExecuted::class, function ($event) {
            if (str_contains($event->sql, 'insert into `access_audits`')) {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        foreach (['role', 'disable'] as $action) {
            try {
                if ($action === 'role') {
                    app(AccessManagement::class)->changeRole($admin->id, $target->id, 'manager', true);
                } else {
                    app(AccessManagement::class)->disable($admin->id, $target->id);
                }
                $this->fail('Audit failure must abort the change');
            } catch (\RuntimeException) {
                $this->assertSame(['basic'], $target->roles());
                $this->assertTrue($target->fresh()->enabled);
                $this->assertSame(0, DB::table('access_audits')->where('target_id', $target->id)->count());
            }
        }
    }

    public function test_audit_entries_have_no_mutation_routes(): void
    {
        $admin = $this->user(['basic', 'admin'], true);
        $this->signedIn($admin)->post('/access-audits/1', [])->assertNotFound();
        $this->delete('/access-audits/1')->assertNotFound();
        $this->put('/access-audits/1', [])->assertNotFound();
    }

    public function test_normal_session_idle_and_absolute_boundaries(): void
    {
        $user = $this->user();
        $now = now()->timestamp;
        $this->signedIn($user, false, $now - 43199, $now - 1799)->get('/users')->assertOk();
        $this->signedIn($user, false, $now - 43200, $now)->get('/users')->assertRedirect('/login');
        $this->signedIn($user, false, $now, $now - 1800)->get('/users')->assertRedirect('/login');
        $this->signedIn($user, false, $now, $now - 1799)->withHeader('Sec-Fetch-User', '?1')->get('/users')->assertOk();
        $this->assertSame($now, session('authentication.activity'));
        $this->assertSame($now, session('authentication.started'));
    }

    public function test_remembered_rolling_boundary_and_background_requests(): void
    {
        $user = $this->user();
        $now = now()->timestamp;
        $this->signedIn($user, true, $now - 900000, $now - 604799)->get('/users')->assertOk();
        $this->assertSame($now - 604799, session('authentication.activity'));
        $this->withHeader('Sec-Fetch-User', '?1')->get('/users')->assertOk();
        $this->assertSame($now, session('authentication.activity'));
        $this->signedIn($user, true, $now - 900000, $now - 604800)->get('/users')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_is_post_only_and_signed_out_page_is_passive(): void
    {
        $user = $this->user();
        $this->signedIn($user, true)->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect('/signed-out');
        $this->assertGuest();
        $this->get('/signed-out')->assertSee('You have been signed out from this site, but not Microsoft.')->assertSee('Sign out of Microsoft');
        $this->signedIn($user)->get('/signed-out')->assertOk();
        $this->assertAuthenticated();
        $this->get('/users')->assertOk();
    }

    public function test_logout_rejects_missing_csrf_when_checks_are_enabled(): void
    {
        $user = $this->user();
        $this->signedIn($user);
        $this->withoutMiddleware(CheckBaseline::class);
        app()->instance('env', 'local');
        $this->post('/logout')->assertStatus(419);
        $this->assertAuthenticated();
    }

    public function test_failed_schema_guard_prevents_preparation_without_changing_data(): void
    {
        $user = $this->user();
        DB::table('migrations')->where('migration', '2026_10_05_010000_create_authentication_tables')->delete();
        $this->artisan('authentication:prepare')->assertExitCode(1);
        $this->assertTrue(DB::table('users')->where('id', $user->id)->exists());
        $this->assertFalse(DB::table('migrations')->where('migration', '2026_10_05_010000_create_authentication_tables')->exists());
    }

    public function test_single_role_authority_and_basic_denials_are_enforced_directly(): void
    {
        $target = $this->user(['basic'], true);
        foreach ([['basic'], ['basic', 'manager'], ['basic', 'procurement'], ['basic', 'admin']] as $roles) {
            $actor = $this->user($roles, true);
            foreach (['admin', 'manager', 'procurement'] as $role) {
                $allowed = in_array('admin', $roles, true) || ($role !== 'admin' && in_array($role, $roles, true));
                $response = $this->signedIn($actor)->post('/users/'.$target->id.'/roles', ['role' => $role, 'grant' => 1]);
                if ($allowed) {
                    $response->assertRedirect('/users');
                    $this->assertTrue($target->hasRole($role));
                    $this->post('/users/'.$target->id.'/roles', ['role' => $role, 'grant' => 0])->assertRedirect('/users');
                } else {
                    $response->assertForbidden();
                    $this->assertFalse($target->hasRole($role));
                }
            }
            if (! in_array('admin', $roles, true)) {
                $this->post('/users/'.$target->id.'/disable')->assertForbidden();
                $this->assertTrue($target->fresh()->enabled);
            }
        }
    }

    public function test_bootstrap_audit_failure_rolls_back_consumption_and_grant(): void
    {
        $user = app(MicrosoftIdentity::class)->resolve($this->external(), (object) ['tid' => self::TENANT, 'oid' => self::OBJECT]);
        Event::listen(QueryExecuted::class, function ($event) {
            if (str_contains($event->sql, 'insert into `access_audits`')) {
                throw new \RuntimeException('Synthetic bootstrap audit failure');
            }
        });
        try {
            app(AccessManagement::class)->saveProfile($user->id, ['first_name' => '', 'last_name' => '', 'contact_email' => 'synthetic-bootstrap@tabletopgaymers.org', 'contact_attested' => true]);
            $this->fail('Bootstrap audit failure must abort');
        } catch (\RuntimeException) {
            $this->assertSame(['basic'], $user->roles());
            $this->assertFalse($user->fresh()->contact_attested);
            $this->assertNull(DB::table('authentication_bootstraps')->where('key', 'initial-admin')->value('consumed_at'));
            $this->assertSame(0, DB::table('access_audits')->where('target_id', $user->id)->count());
        }
    }

    public function test_microsoft_logout_requires_explicit_post_and_fixed_return(): void
    {
        $this->get('/signed-out')->assertOk();
        $this->post('/auth/microsoft/logout', ['return' => 'https://unapproved.test'])->assertRedirect();
        $this->assertTrue(session('microsoft_logout_requested'));
        $this->get('/signed-out')->assertSee('Your requested Microsoft sign-out has returned.')->assertDontSee('You have been signed out from this site, but not Microsoft.');
        $this->assertNull(session('microsoft_logout_requested'));
    }

    public function test_change_failure_does_not_leave_an_audit_entry(): void
    {
        $admin = $this->user(['basic', 'admin'], true);
        $target = $this->user(['basic'], true);
        Event::listen(QueryExecuted::class, function ($event) {
            if (str_contains($event->sql, 'insert into `user_roles`')) {
                throw new \RuntimeException('Synthetic role-save failure');
            }
        });
        try {
            app(AccessManagement::class)->changeRole($admin->id, $target->id, 'manager', true);
            $this->fail('Role-save failure must abort');
        } catch (\RuntimeException) {
            $this->assertSame(['basic'], $target->roles());
            $this->assertSame(0, DB::table('access_audits')->where('target_id', $target->id)->count());
        }
    }

    public function test_remembered_database_session_resumes_from_persistent_secure_cookie(): void
    {
        config(['session.driver' => 'database']);
        $provider = Mockery::mock();
        $provider->shouldReceive('setScopes')->andReturnSelf();
        $provider->shouldReceive('enablePKCE')->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($this->external());
        $provider->shouldReceive('getClaims')->once()->andReturn((object) ['tid' => self::TENANT, 'oid' => self::OBJECT]);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);
        $this->withSession(['microsoft_attempt' => ['started' => now()->timestamp, 'remember' => true]]);
        $response = $this->get('/auth/microsoft/callback?code=synthetic-code');
        $response->assertRedirect('/');
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(now()->timestamp + 600000, $cookie->getExpiresTime());
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get('/users')->assertOk();
        $this->assertAuthenticated();
    }
}
