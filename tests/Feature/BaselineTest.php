<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class BaselineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || ! app(BaselineProbe::class)->inspect()['ready']) {
            throw new RuntimeException('The isolated test database is not ready. Follow the local setup instructions.');
        }
    }

    public function test_the_page_reports_the_verified_database_without_connection_settings(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('LOCAL DEVELOPMENT BASELINE')
            ->assertSee('Database check passed.')
            ->assertSee(PHP_VERSION)
            ->assertDontSee('tg_inventory_test')
            ->assertDontSee('DB_PASSWORD');
    }

    public function test_session_storage_round_trip_uses_only_a_temporary_record(): void
    {
        $connection = DB::connection('mariadb');
        $id = 'baseline-check-'.bin2hex(random_bytes(16));
        $handler = new DatabaseSessionHandler($connection, 'sessions', 120);
        try {
            $this->assertTrue($handler->write($id, 'temporary-baseline-check'));
            $this->assertSame('temporary-baseline-check', $handler->read($id));
        } finally {
            $handler->destroy($id);
        }
        $this->assertFalse($connection->table('sessions')->where('id', $id)->exists());
    }

    public function test_incorrect_database_configuration_returns_a_safe_unavailable_page(): void
    {
        config(['database.connections.mariadb.database' => 'unapproved_database']);
        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Development configuration needs attention.')
            ->assertDontSee('unapproved_database')
            ->assertDontSee('Database check passed.');
    }

    public function test_rejected_credentials_return_a_safe_failure_without_logging_them(): void
    {
        config(['database.connections.mariadb.password' => 'intentional-invalid-check-value']);
        DB::purge('mariadb');
        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Database check failed.')
            ->assertDontSee('intentional-invalid-check-value')
            ->assertDontSee('tg_inventory_test')
            ->assertDontSee('SQLSTATE');
    }

    public function test_a_missing_key_is_not_reported_as_ready(): void
    {
        config(['app.key' => '']);
        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Development configuration needs attention.')
            ->assertDontSee('Database check passed.');
    }

    public function test_the_command_returns_success_for_the_ready_baseline(): void
    {
        $this->artisan('baseline:check')->assertExitCode(0);
    }

    public function test_the_command_returns_failure_for_incorrect_configuration(): void
    {
        config(['database.connections.mariadb.database' => 'unapproved_database']);
        $this->artisan('baseline:check')->assertExitCode(1);
    }
}
