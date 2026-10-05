<?php

namespace Tests\Feature;

use App\Session\BaselineDatabaseSessionHandler;
use App\Support\BaselineProbe;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $handler = new BaselineDatabaseSessionHandler($connection, 'sessions', 120);
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

    #[DataProvider('sessionFailures')]
    public function test_downstream_session_database_failures_are_safe(string $operation, bool $queryException): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('useWritePdo', 'where')->andReturnSelf();
        $fail = function () use ($queryException) {
            $previous = new PDOException('synthetic-private-credential SQLSTATE connection');
            throw $queryException
                ? new QueryException('synthetic-private-connection', 'synthetic-private-SQL', [], $previous)
                : $previous;
        };
        if ($operation === 'read') {
            $query->shouldReceive('find')->once()->andReturnUsing($fail);
        } else {
            $query->shouldReceive('find')->andReturn((object) [
                'payload' => '', 'last_activity' => time(),
            ]);
            $query->shouldReceive($operation === 'write' ? 'update' : 'delete')->once()->andReturnUsing($fail);
        }
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('table')->with('sessions')->andReturn($query);
        app('session')->extend('database', fn () => new BaselineDatabaseSessionHandler($connection, 'sessions', 120));
        config(['session.driver' => 'database', 'session.lottery' => $operation === 'gc' ? [1, 1] : [0, 1]]);
        Log::spy();

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Database session check failed. Run the documented baseline checks.')
            ->assertDontSee('Database check passed.')
            ->assertDontSee('synthetic-private')
            ->assertDontSee('SQLSTATE');
        Log::shouldNotHaveReceived('error');
    }

    public static function sessionFailures(): array
    {
        return [
            'PDO read' => ['read', false], 'query read' => ['read', true],
            'PDO write' => ['write', false], 'query write' => ['write', true],
            'PDO collection' => ['gc', false], 'query collection' => ['gc', true],
        ];
    }

    #[DataProvider('insertRecoveryCases')]
    public function test_session_insert_recovery_through_the_registered_handler(string $case, int $status): void
    {
        $baseline = app(BaselineProbe::class)->inspect();
        $probe = Mockery::mock(BaselineProbe::class);
        $probe->shouldReceive('inspect')->once()->andReturn($baseline);
        app()->instance(BaselineProbe::class, $probe);
        $payload = null;
        $reads = 0;
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('useWritePdo', 'where')->andReturnSelf();
        $query->shouldReceive('find')->andReturnUsing(function () use (&$reads, &$payload, $case) {
            $reads++;
            if ($reads <= 2 || $case === 'missing') {
                return null;
            }
            $saved = (object) $payload;
            if ($case === 'mismatch') {
                $saved->payload = 'synthetic-unsaved-payload';
            }

            if ($case === 'metadata-mismatch') {
                $saved->last_activity--;
            }
            if ($case === 'missing-column') {
                unset($saved->payload);
            }

            return $saved;
        });
        $query->shouldReceive('insert')->once()->andReturnUsing(function ($data) use (&$payload, $case) {
            $payload = $data;
            if ($case === 'fresh') {
                return true;
            }
            $error = new PDOException('synthetic-private-credential SQLSTATE connection');
            throw $case === 'pdo' ? $error : new QueryException('synthetic-private-connection', 'synthetic-private-SQL', [], $error);
        });
        if (! in_array($case, ['fresh', 'pdo'], true)) {
            $query->shouldReceive('update')->once()->andReturnUsing(function () use ($case) {
                if ($case === 'update-error') {
                    throw new QueryException('synthetic-private-connection', 'synthetic-private-SQL', [], new PDOException('synthetic-private-credential'));
                }

                return $case === 'updated' ? 1 : 0;
            });
        }
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('table')->with('sessions')->andReturn($query);
        DB::shouldReceive('connection')->with(null)->andReturn($connection);
        config(['session.driver' => 'database', 'session.connection' => null, 'session.lottery' => [0, 1], 'session.encrypt' => true]);
        $this->assertInstanceOf(BaselineDatabaseSessionHandler::class, app('session')->driver()->getHandler());
        Log::spy();
        $response = $this->get('/')->assertStatus($status)
            ->assertDontSee('synthetic-private')->assertDontSee('SQLSTATE');
        if ($status === 503) {
            $response->assertSee('Database session check failed. Run the documented baseline checks.')
                ->assertDontSee('Database check passed.');
        } else {
            $response->assertSee('Database check passed.')->assertDontSee('Database session check failed.');
        }
        Log::shouldNotHaveReceived('error');
    }

    public static function insertRecoveryCases(): array
    {
        return [
            'missing fallback row' => ['missing', 503],
            'unchanged but wrong payload' => ['mismatch', 503],
            'unchanged but wrong metadata' => ['metadata-mismatch', 503],
            'incomplete saved row' => ['missing-column', 503],
            'successful fallback update' => ['updated', 200],
            'successful fallback no-op' => ['identical', 200],
            'insert PDO failure' => ['pdo', 503],
            'fallback query failure' => ['update-error', 503],
            'healthy fresh insert' => ['fresh', 200],
        ];
    }

    public function test_an_existing_session_zero_row_update_remains_successful(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('useWritePdo', 'where')->andReturnSelf();
        $query->shouldReceive('find')->once()->andReturn((object) ['payload' => '', 'last_activity' => time()]);
        $query->shouldReceive('update')->once()->andReturn(0);
        $query->shouldNotReceive('insert');
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('table')->with('sessions')->andReturn($query);
        app('session')->extend('database', fn () => new BaselineDatabaseSessionHandler($connection, 'sessions', 120));
        config(['session.driver' => 'database', 'session.lottery' => [0, 1]]);
        Log::spy();
        $this->get('/')->assertOk()->assertSee('Database check passed.');
        Log::shouldNotHaveReceived('error');
    }

    #[DataProvider('unrelatedFailures')]
    public function test_unrelated_exceptions_are_still_reported_normally(bool $database): void
    {
        Route::get('/baseline-unrelated-error', function () use ($database) {
            throw $database ? new PDOException('synthetic-unrelated-error') : new RuntimeException('synthetic-unrelated-error');
        });
        Log::spy();
        $this->get('/baseline-unrelated-error')->assertStatus(500)
            ->assertDontSee('Database session check failed.');
        Log::shouldHaveReceived('error')->once();
    }

    public static function unrelatedFailures(): array
    {
        return [['database' => true], ['database' => false]];
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
