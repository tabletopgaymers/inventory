<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\CatalogSchema;
use App\Support\InventorySchema;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HostedBaselineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->instance('env', 'development');
        config([
            'app.debug' => false,
            'app.url' => 'https://dev-inventory.tabletopgaymers.org',
            'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'database.default' => 'mariadb',
            'database.connections.mariadb.host' => '127.0.0.1',
            'database.connections.mariadb.port' => 3306,
            'database.connections.mariadb.database' => 'tg_inventory_dev',
            'database.connections.mariadb.username' => 'tg_inventory_dev',
            'database.connections.mariadb.password' => 'dummy-test-value',
            'database.connections.mariadb.url' => null,
            'database.connections.mariadb.unix_socket' => '',
            'database.connections.mariadb.prefix' => '',
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.domain' => null,
            'session.path' => '/',
        ]);
    }

    public function test_hosted_configuration_and_mariadb_patch_version_are_accepted_without_live_access(): void
    {
        $database = Mockery::mock();
        $database->shouldReceive('selectOne')->once()->andReturn((object) [
            'probe' => 1, 'database_name' => 'tg_inventory_dev', 'version' => '10.11.14-MariaDB',
        ]);
        DB::shouldReceive('connection')->once()->with('mariadb')->andReturn($database);
        $result = app(BaselineProbe::class)->inspect(requireSessions: false);
        $this->assertTrue($result['ready']);
        $this->assertSame('HOSTED DEVELOPMENT BASELINE', $result['baselineLabel']);
        $this->assertSame('10.11.14', $result['databaseVersion']);
    }

    #[DataProvider('invalidSettings')]
    public function test_hosted_rejects_unsafe_settings_before_any_database_access(string $key, mixed $value): void
    {
        config([$key => $value]);
        DB::shouldReceive('connection')->never();
        $result = app(BaselineProbe::class)->inspect();
        $this->assertFalse($result['ready']);
        $this->assertStringNotContainsString('dummy-test-value', $result['message']);
    }

    public static function invalidSettings(): array
    {
        return [
            ['database.connections.mariadb.database', 'tg_inventory_local'],
            ['database.connections.mariadb.username', 'root'],
            ['database.connections.mariadb.host', 'remote-host'],
            ['database.connections.mariadb.port', 3311],
            ['database.connections.mariadb.url', 'mysql://dummy'],
            ['database.connections.mariadb.unix_socket', '/tmp/mysql.sock'],
            ['database.connections.mariadb.prefix', 'other_'],
            ['database.connections.mariadb.password', ''],
            ['app.key', ''], ['app.debug', true],
            ['app.url', 'http://dev-inventory.tabletopgaymers.org'],
            ['session.driver', 'file'], ['session.encrypt', false],
            ['session.secure', false], ['session.http_only', false],
            ['session.same_site', 'none'], ['session.domain', '.tabletopgaymers.org'],
            ['session.connection', 'mysql'], ['session.path', '/other'],
        ];
    }

    public function test_unsupported_environment_is_rejected(): void
    {
        app()->instance('env', 'production');
        DB::shouldReceive('connection')->never();
        $this->assertFalse(app(BaselineProbe::class)->inspect()['ready']);
    }

    public function test_hosted_warning_is_safe_and_identifies_hosted_development(): void
    {
        config(['app.key' => '']);
        DB::shouldReceive('connection')->never();
        $this->get('/')->assertStatus(503)->assertSee('HOSTED DEVELOPMENT BASELINE')
            ->assertDontSee('tg_inventory_dev')->assertDontSee('dummy-test-value');
    }

    public function test_hosted_prepare_requires_explicit_option_without_migrating(): void
    {
        $database = Mockery::mock();
        $database->shouldReceive('selectOne')->once()->andReturn((object) [
            'probe' => 1, 'database_name' => 'tg_inventory_dev', 'version' => '10.11.14-MariaDB',
        ]);
        DB::shouldReceive('connection')->once()->andReturn($database);
        $this->artisan('baseline:prepare')->assertExitCode(1);
    }

    #[DataProvider('schemaCases')]
    public function test_schema_inspection_guards_existing_data(array $tables, bool $recorded, array $columns, bool $ready, bool $canPrepare): void
    {
        $schema = Mockery::mock();
        $database = Mockery::mock();
        $database->shouldReceive('getSchemaBuilder')->andReturn($schema);
        $schema->shouldReceive('getTableListing')->with(null, false)->andReturn($tables);
        if (in_array('migrations', $tables)) {
            $ledger = Mockery::mock();
            $sessionRecord = Mockery::mock();
            $stockRecord = Mockery::mock();
            $database->shouldReceive('table')->with('migrations')->andReturn($ledger);
            $ledger->shouldReceive('where')->with('migration', '2026_10_05_000000_create_sessions_table')->andReturn($sessionRecord);
            $sessionRecord->shouldReceive('exists')->andReturn($recorded);
            $ledger->shouldReceive('where')->with('migration', InventorySchema::MIGRATION)->andReturn($stockRecord);
            $stockRecord->shouldReceive('exists')->andReturn(false);
            $catalogRecord = Mockery::mock();
            $ledger->shouldReceive('where')->with('migration', CatalogSchema::MIGRATION)->andReturn($catalogRecord);
            $catalogRecord->shouldReceive('exists')->andReturn(false);
        }
        if (in_array('sessions', $tables)) {
            $schema->shouldReceive('getColumnListing')->with('sessions')->andReturn($columns);
        }
        $state = app(BaselineProbe::class)->schemaState($database);
        $this->assertSame($ready, $state['ready']);
        $this->assertSame($canPrepare, $state['canPrepare']);
    }

    public static function schemaCases(): array
    {
        $columns = ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'];

        return [
            [[], false, [], false, true],
            [['migrations'], false, [], false, true],
            [['sessions'], false, $columns, false, false],
            [['migrations'], true, [], false, false],
            [['migrations', 'sessions'], true, $columns, true, true],
            [['migrations', 'sessions'], true, ['id'], false, false],
            [['migrations', 'sessions', 'unrelated'], true, $columns, false, false],
        ];
    }
}
