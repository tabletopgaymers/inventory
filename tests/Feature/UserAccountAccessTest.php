<?php

namespace Tests\Feature;

use App\Http\Controllers\MicrosoftAuthController;
use App\Http\Controllers\UserController;
use App\Models\User;
use App\Support\AccessManagement;
use App\Support\MicrosoftConfiguration;
use App\Support\ProfilePreferences;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserAccountAccessTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        // No development secrets, external database, real sessions or provider calls.
        $app->loadEnvironmentFrom('.env.account-access-test-nonexistent');
        $app->make(Kernel::class)->bootstrap();
        $app->detectEnvironment(fn () => 'testing');
        $app['config']->set([
            'database.default' => 'account_access_test',
            'database.connections' => ['account_access_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => true,
            ]],
            'session.driver' => 'array', 'cache.default' => 'array',
            'logging.default' => 'null', 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'services.microsoft.tenant' => '11111111-1111-1111-1111-111111111111',
        ]);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $migration = require base_path('database/migrations/2026_10_05_010000_create_authentication_tables.php');
        $migration->up();
        Schema::table('users', fn ($table) => $table->string('time_zone')->nullable());
    }

    private function account(array $roles, bool $enabled = true): User
    {
        $user = User::create(['first_name' => 'Synthetic', 'last_name' => 'Account',
            'enabled' => $enabled, 'contact_email' => 'fixture@tabletopgaymers.org', 'contact_attested' => true]);
        foreach ($roles as $role) {
            DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => $role]);
        }

        return $user;
    }

    public function test_disable_clears_every_role_and_audits_both_changes_once(): void
    {
        $admin = $this->account(['admin']);
        $target = $this->account(AccessManagement::ROLES);
        $access = new AccessManagement;
        $access->disable($admin->id, $target->id);
        $this->assertFalse($target->fresh()->enabled);
        $this->assertSame([], $target->roles());
        $this->assertSame(['admin'], $admin->roles());
        $audit = DB::table('access_audits')->orderBy('id')->get();
        $this->assertSame(['roles', 'disablement'], $audit->pluck('action')->all());
        $this->assertSame(['admin', 'basic', 'manager', 'procurement'], json_decode($audit[0]->previous, true));
        $this->assertSame([], json_decode($audit[0]->current, true));
        $this->assertSame(['enabled' => false], json_decode($audit[1]->current, true));
        $access->disable($admin->id, $target->id);
        $this->assertSame(2, DB::table('access_audits')->count());
    }

    public function test_enable_restores_access_without_reactivating_legacy_roles(): void
    {
        $admin = $this->account(['admin']);
        $target = $this->account(['basic', 'manager'], false);
        $access = new AccessManagement;
        $access->enable($admin->id, $target->id);
        $this->assertTrue($target->fresh()->enabled);
        $this->assertSame([], $target->roles());
        $this->assertSame(['roles', 'enablement'], DB::table('access_audits')->orderBy('id')->pluck('action')->all());
        $access->enable($admin->id, $target->id);
        $this->assertSame(2, DB::table('access_audits')->count());
    }

    public function test_enable_of_an_enabled_account_preserves_roles(): void
    {
        $admin = $this->account(['admin']);
        $target = $this->account(['manager']);
        (new AccessManagement)->enable($admin->id, $target->id);
        $this->assertSame(['manager'], $target->roles());
        $this->assertSame(0, DB::table('access_audits')->count());
    }

    public function test_only_an_enabled_admin_can_enable_or_disable(): void
    {
        $target = $this->account(['manager']);
        foreach ([$this->account(['basic']), $this->account(['manager', 'procurement']), $this->account(['admin'], false)] as $actor) {
            foreach (['disable', 'enable'] as $action) {
                try {
                    (new AccessManagement)->{$action}($actor->id, $target->id);
                    $this->fail('Unauthorized access change succeeded.');
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                }
            }
        }
        $this->assertTrue($target->fresh()->enabled);
        $this->assertSame(['manager'], $target->roles());
        $this->assertSame(0, DB::table('access_audits')->count());
    }

    public function test_disabled_accounts_cannot_receive_new_roles(): void
    {
        $admin = $this->account(['admin']);
        $target = $this->account([], false);
        try {
            (new AccessManagement)->changeRole($admin->id, $target->id, 'manager', true);
            $this->fail('Disabled account received a role.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Enable this account before granting roles.'], $exception->errors()['role']);
        }
        $this->assertSame([], $target->roles());
    }

    public function test_audit_failure_rolls_back_roles_and_disablement(): void
    {
        $admin = $this->account(['admin']);
        $target = $this->account(['basic', 'manager']);
        DB::unprepared("CREATE TRIGGER reject_access_audit BEFORE INSERT ON access_audits WHEN NEW.action = 'disablement' BEGIN SELECT RAISE(ABORT, 'synthetic audit failure'); END");
        try {
            (new AccessManagement)->disable($admin->id, $target->id);
            $this->fail('Change succeeded without its audit.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertTrue($target->fresh()->enabled);
            $this->assertSame(['basic', 'manager'], $target->roles());
            $this->assertSame(0, DB::table('access_audits')->count());
        }
    }

    private function callbackRequest(User $target, bool $providerFailure = false): Request
    {
        $object = '22222222-2222-2222-2222-222222222222';
        DB::table('external_identities')->insert(['user_id' => $target->id, 'provider' => 'microsoft',
            'tenant_id' => config('services.microsoft.tenant'), 'object_id' => $object,
            'created_at' => now(), 'updated_at' => now()]);
        $external = new class($object) {
            public function __construct(private string $object) {}
            public function getId() { return $this->object; }
            public function getEmail() { return 'synthetic@example.test'; }
        };
        $configuration = Mockery::mock(MicrosoftConfiguration::class);
        $configuration->shouldReceive('ready')->andReturn(true);
        $this->app->instance(MicrosoftConfiguration::class, $configuration);
        $provider = Mockery::mock();
        $provider->shouldReceive('setScopes')->andReturnSelf();
        $provider->shouldReceive('enablePKCE')->andReturnSelf();
        if ($providerFailure) {
            $provider->shouldReceive('user')->andThrow(new \RuntimeException('Synthetic provider failure.'));
        } else {
            $provider->shouldReceive('user')->andReturn($external);
            $provider->shouldReceive('getClaims')->andReturn((object) ['tid' => config('services.microsoft.tenant'), 'oid' => $object]);
        }
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);
        Log::spy();
        $request = Request::create('/auth/microsoft/callback');
        $session = $this->app['session']->driver();
        $request->setLaravelSession($session);
        $this->app->instance('session.store', $session);
        $this->app->instance('request', $request);
        $session->put('microsoft_attempt', ['started' => now()->timestamp, 'remember' => true]);
        $session->put('state', 'synthetic-state');
        $session->put('code_verifier', 'synthetic-verifier');

        return $request;
    }

    public function test_disabled_sign_in_has_specific_message_and_creates_no_session(): void
    {
        $target = $this->account([], false);
        $request = $this->callbackRequest($target);
        $response = (new MicrosoftAuthController)->callback($request);
        $this->assertSame(url('/login'), $response->getTargetUrl());
        $this->assertSame('This account has been disabled. Contact your administrator.', $request->session()->get('error'));
        $this->assertFalse(Auth::check());
        $this->assertFalse($request->session()->has('authentication'));
        $this->assertFalse($request->session()->has('state'));
        $this->assertFalse($request->session()->has('code_verifier'));
        $this->assertFalse($request->session()->has('microsoft_attempt'));
        $this->assertNull($target->fresh()->provider_email);
        $this->assertSame(1, User::count());
        $this->assertSame([], $target->roles());
    }

    public function test_provider_failure_keeps_the_generic_sign_in_message(): void
    {
        $request = $this->callbackRequest($this->account([]), true);
        (new MicrosoftAuthController)->callback($request);
        $this->assertSame('Sign-in could not be completed. Please start again.', $request->session()->get('error'));
        $this->assertFalse(Auth::check());
    }

    public function test_enabled_sign_in_still_succeeds_without_restoring_roles(): void
    {
        $target = $this->account([]);
        $request = $this->callbackRequest($target);
        $response = (new MicrosoftAuthController)->callback($request);
        $this->assertSame(url('/'), $response->getTargetUrl());
        $this->assertTrue(Auth::check());
        $this->assertTrue($request->session()->get('authentication.remember'));
        $this->assertSame([], $target->roles());
    }

    public function test_user_view_enable_control_is_admin_only_and_has_no_disabled_role_controls(): void
    {
        $target = $this->account([], false);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        Auth::setUser($this->account(['admin']));
        $html = view('user-show', ['user' => $target])->render();
        $this->assertStringContainsString('Enable Account', $html);
        $this->assertStringContainsString('/users/'.$target->id.'/enable', $html);
        $this->assertStringNotContainsString('/users/'.$target->id.'/roles', $html);
        Auth::setUser($this->account(['manager']));
        $this->assertStringNotContainsString('Enable Account', view('user-show', ['user' => $target])->render());
    }

    private function readyProfile(): void
    {
        // SQLite fixture tests access/profile rules; production schema guard is unchanged.
        $preferences = Mockery::mock(ProfilePreferences::class);
        $preferences->shouldReceive('state')->andReturn(['ready' => true, 'compatible' => true]);
        $this->app->instance(ProfilePreferences::class, $preferences);
    }

    private function profileData(): array
    {
        return ['first_name' => 'Updated', 'last_name' => 'Person',
            'contact_email' => 'changed@tabletopgaymers.org', 'contact_attested' => true,
            'time_zone' => 'America/New_York'];
    }

    public function test_admin_can_edit_another_profile_without_attesting_or_bootstrapping_roles(): void
    {
        $this->readyProfile();
        $admin = $this->account(['admin']);
        $target = $this->account(['manager']);
        $object = '22222222-2222-2222-2222-222222222222';
        config(['microsoft_auth.bootstrap_tenant' => config('services.microsoft.tenant'), 'microsoft_auth.bootstrap_object' => $object]);
        DB::table('external_identities')->insert(['user_id' => $target->id, 'provider' => 'microsoft',
            'tenant_id' => config('services.microsoft.tenant'), 'object_id' => $object,
            'created_at' => now(), 'updated_at' => now()]);
        (new AccessManagement)->saveProfile($target->id, $this->profileData(), $admin->id);
        $target = $target->fresh();
        $this->assertSame('Updated', $target->first_name);
        $this->assertSame('America/New_York', $target->time_zone);
        $this->assertSame('changed@tabletopgaymers.org', $target->contact_email);
        $this->assertFalse($target->contact_attested);
        $this->assertSame(['manager'], $target->roles());
        $this->assertNull(DB::table('authentication_bootstraps')->value('consumed_at'));
        $this->assertSame('Synthetic', $admin->fresh()->first_name);
        $this->assertNull($target->provider_email);
    }

    public function test_admin_can_edit_disabled_profile_without_enabling_it(): void
    {
        $this->readyProfile();
        $admin = $this->account(['admin']);
        $target = $this->account([], false);
        (new AccessManagement)->saveProfile($target->id, $this->profileData(), $admin->id);
        $this->assertSame('Updated', $target->fresh()->first_name);
        $this->assertFalse($target->fresh()->enabled);
        $this->assertSame([], $target->roles());
    }

    public function test_own_profile_still_allows_self_confirmation(): void
    {
        $this->readyProfile();
        $owner = $this->account([]);
        (new AccessManagement)->saveProfile($owner->id, $this->profileData());
        $this->assertTrue($owner->fresh()->validContact());
        $this->assertSame([], $owner->roles());
    }

    public function test_other_profile_reads_and_writes_reject_non_admins(): void
    {
        $this->readyProfile();
        $target = $this->account([]);
        foreach ([$this->account(['basic']), $this->account(['manager', 'procurement']), $this->account(['admin'], false)] as $actor) {
            $request = Request::create('/profile?user='.$target->id);
            $request->setUserResolver(fn () => $actor);
            try {
                (new UserController)->profile($request);
                $this->fail('Unauthorized profile read succeeded.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            try {
                (new AccessManagement)->saveProfile($target->id, $this->profileData(), $actor->id);
                $this->fail('Unauthorized profile write succeeded.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame('Synthetic', $target->fresh()->first_name);
    }

    public function test_edit_button_and_profile_editor_use_the_correct_account(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $target = $this->account(['basic']);
        $target->update(['time_zone' => 'America/New_York']);
        $admin = $this->account(['admin']);
        Auth::setUser($admin);
        $userView = view('user-show', ['user' => $target])->render();
        $this->assertStringContainsString('href="/profile?user='.$target->id.'">Edit', $userView);
        $this->assertStringNotContainsString('All users', $userView);
        $request = Request::create('/profile?user='.$target->id);
        $request->setUserResolver(fn () => $admin);
        $editor = (new UserController)->profile($request);
        $this->assertSame($target->id, $editor->getData()['profileUser']->id);
        $html = $editor->render();
        $this->assertStringContainsString('action="/profile?user='.$target->id.'"', $html);
        $this->assertStringContainsString('America/New_York', $html);
        $this->assertStringNotContainsString('name="contact_attested"', $html);
        Auth::setUser($target);
        $this->assertStringContainsString('href="/profile">Edit', view('user-show', ['user' => $target])->render());
        Auth::setUser($this->account(['manager']));
        $this->assertStringNotContainsString('>Edit</a>', view('user-show', ['user' => $target])->render());
    }
}
