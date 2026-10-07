<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\DisplayDates;
use App\Support\ProfilePreferences;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfilePreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(ProfilePreferences::class)->state(DB::connection())['ready']);
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_profile_zone_persistence_validation_default_and_timestamp_boundaries(): void
    {
        $user = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
        $this->actingAs($user)->withSession(['authentication' => ['started' => now()->timestamp, 'activity' => now()->timestamp, 'remember' => false]]);
        $this->assertSame('America/Chicago', DisplayDates::effectiveZone());
        $this->post('/profile', ['first_name' => 'Retain this', 'time_zone' => 'Mars/Olympus'])->assertSessionHasErrors('time_zone')->assertSessionHasInput('first_name', 'Retain this');
        $this->assertSame('Test', $user->fresh()->first_name);
        $this->assertNull($user->fresh()->time_zone);
        $this->post('/profile', ['first_name' => 'Test', 'last_name' => 'Viewer', 'time_zone' => 'Asia/Tokyo'])->assertRedirect('/profile');
        $this->get('/profile')->assertOk()->assertSee('Effective time zone: Asia/Tokyo');
        $this->assertSame('Asia/Tokyo', $user->fresh()->time_zone);
        $this->assertSame('08 Oct 2026', DisplayDates::date('2026-10-07 23:30:00'));
        $this->assertSame('08 Oct 2026 8:30 AM JST', DisplayDates::timestamp('2026-10-07 23:30:00'));
        $this->assertSame('07 Oct 2026', DisplayDates::businessDate('2026-10-07'));
        Auth::logout();
        $this->assertSame('America/Chicago', DisplayDates::effectiveZone());
    }

    public function test_orphan_preference_column_blocks_preparation_and_readiness_without_data_loss(): void
    {
        $user = User::create(['first_name' => 'Test', 'last_name' => 'Viewer', 'time_zone' => 'Europe/London']);
        DB::table('migrations')->where('migration', ProfilePreferences::MIGRATION)->delete();
        $service = app(ProfilePreferences::class);
        $this->assertSame(['compatible' => false, 'ready' => false], $service->state(DB::connection()));
        $this->assertFalse(app(BaselineProbe::class)->schemaState(DB::connection())['canPrepare']);
        $this->assertFalse($service->prepare(false));
        $this->artisan('profile-preferences:check')->assertExitCode(1);
        $this->assertSame('Europe/London', $user->fresh()->time_zone);
    }

    public function test_preference_schema_exact_contract_and_pending_state(): void
    {
        $column = ['name' => 'time_zone', 'type' => 'varchar(100)', 'nullable' => true, 'default' => 'NULL', 'auto_increment' => false, 'generation' => null];
        $cases = [
            [false, null, true, false],
            [true, null, false, false],
            [false, $column, false, false],
            [true, $column, true, true],
            [true, array_replace($column, ['type' => 'varchar(255)']), false, false],
            [true, array_replace($column, ['nullable' => false]), false, false],
            [true, array_replace($column, ['default' => "'UTC'"]), false, false],
        ];
        foreach ($cases as [$recorded, $actual, $compatible, $ready]) {
            $database = \Mockery::mock();
            $schema = \Mockery::mock();
            $ledger = \Mockery::mock();
            $database->shouldReceive('getSchemaBuilder')->andReturn($schema);
            $schema->shouldReceive('getColumns')->with('users')->andReturn($actual ? [$actual] : []);
            $database->shouldReceive('table')->with('migrations')->andReturn($ledger);
            $ledger->shouldReceive('where')->with('migration', ProfilePreferences::MIGRATION)->andReturnSelf();
            $ledger->shouldReceive('exists')->andReturn($recorded);
            $this->assertSame(['compatible' => $compatible, 'ready' => $ready], app(ProfilePreferences::class)->state($database, ['users', 'migrations']));
        }
    }
}
