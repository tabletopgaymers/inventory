<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use App\Support\EventPreparation;
use App\Support\EventSchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EventDeploymentTest extends TestCase
{
    #[DataProvider('refusedEnvironments')]
    public function test_environment_refusal_precedes_database_or_migration_access(string $environment, bool $hosted): void
    {
        app()->instance('env', $environment);
        $probe = Mockery::mock(BaselineProbe::class);
        $probe->shouldNotReceive('inspect');
        $this->app->instance(BaselineProbe::class, $probe);
        DB::shouldReceive('connection')->never();
        Artisan::shouldReceive('call')->never();
        $this->assertFalse(app(EventPreparation::class)->prepare($hosted));
    }

    public static function refusedEnvironments(): array
    {
        return [['production', false], ['production', true], ['unknown', true], ['development', false]];
    }

    public function test_invalid_baseline_identity_refuses_explicit_hosted_preparation(): void
    {
        app()->instance('env', 'development');
        $probe = Mockery::mock(BaselineProbe::class);
        $probe->shouldReceive('inspect')->once()->andReturn(['ready' => false]);
        $this->app->instance(BaselineProbe::class, $probe);
        DB::shouldReceive('connection')->never();
        Artisan::shouldReceive('call')->never();
        $this->assertFalse(app(EventPreparation::class)->prepare(true));
    }

    #[DataProvider('unsafeStates')]
    public function test_missing_prerequisite_or_partial_or_orphan_contract_never_migrates(array $state): void
    {
        app()->instance('env', 'development');
        $database = new \stdClass;
        $probe = Mockery::mock(BaselineProbe::class);
        $probe->shouldReceive('inspect')->once()->andReturn(['ready' => true]);
        $probe->shouldReceive('schemaState')->once()->with($database)->andReturn($state);
        $this->app->instance(BaselineProbe::class, $probe);
        DB::shouldReceive('connection')->once()->andReturn($database);
        Artisan::shouldReceive('call')->never();
        $this->assertFalse(app(EventPreparation::class)->prepare(true));
    }

    public static function unsafeStates(): array
    {
        return [[['fulfillmentReady' => false, 'canPrepare' => true]], [['fulfillmentReady' => true, 'canPrepare' => false]]];
    }

    #[DataProvider('completionStates')]
    public function test_explicit_development_opt_in_runs_only_exact_additive_path_and_requires_completed_readiness(bool $ready): void
    {
        app()->instance('env', 'development');
        $database = new \stdClass;
        $probe = Mockery::mock(BaselineProbe::class);
        $probe->shouldReceive('inspect')->once()->andReturn(['ready' => true]);
        $probe->shouldReceive('schemaState')->once()->with($database)->andReturn(['fulfillmentReady' => true, 'canPrepare' => true]);
        $probe->shouldReceive('schemaState')->once()->with($database)->andReturn(['eventsReady' => $ready]);
        $this->app->instance(BaselineProbe::class, $probe);
        DB::shouldReceive('connection')->twice()->andReturn($database);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true, '--no-interaction' => true, '--path' => 'database/migrations/'.EventSchema::MIGRATION.'.php'])->andReturn(0);
        $this->assertSame($ready, app(EventPreparation::class)->prepare(true));
    }

    public static function completionStates(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('completionStates')]
    public function test_console_forwards_only_explicit_hosted_option(bool $hosted): void
    {
        $installer = Mockery::mock(EventPreparation::class);
        $installer->shouldReceive('prepare')->once()->with($hosted)->andReturn(true);
        $this->app->instance(EventPreparation::class, $installer);
        $this->artisan('events:prepare', $hosted ? ['--hosted' => true] : [])->assertExitCode(0);
    }

    public function test_release_gate_preserves_order_and_stops_at_each_event_failure_without_running_commands(): void
    {
        // Child fake Process records commands but never executes artisan or touches a DB.
        $fake = <<<'PHP'
namespace Symfony\Component\Process {
    class Process {
        public static array $commands = [];
        public static string $failure;
        private array $command;
        public function __construct(array $command, string $root) { $this->command=$command; }
        public function setTimeout(int $timeout): void { if($timeout!==120) throw new \RuntimeException; }
        public function mustRun(): void {
            self::$commands[]=$this->command;
            if(self::$failure!=='' && ($this->command[2] ?? '')===self::$failure) throw new \RuntimeException('Controlled child failure');
        }
    }
}
namespace {
    \Symfony\Component\Process\Process::$failure=$argv[1];
    register_shutdown_function(function() { file_put_contents($GLOBALS['argv'][2],json_encode(\Symfony\Component\Process\Process::$commands)); });
    require getcwd().'/scripts/authentication-release-gate.php';
}
PHP;
        $directory = base_path('_private/event-release-tests');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $expected = ['scripts/release-gate.php', 'config:clear', 'baseline:check', 'authentication:configuration', 'authentication:prepare', 'inventory:prepare', 'catalog:prepare', 'daily-inventory:prepare', 'requests:prepare', 'fulfillment:prepare', 'events:prepare', 'config:cache', 'authentication:check', 'inventory:check', 'catalog:check', 'daily-inventory:check', 'requests:check', 'fulfillment:check', 'events:check', 'samples:seed', 'view:cache'];
        foreach (['', 'events:prepare', 'events:check'] as $failure) {
            $receipt = $directory.'/'.bin2hex(random_bytes(6)).'.json';
            $child = new Process([PHP_BINARY, '-r', $fake, $failure, $receipt], base_path());
            $child->setTimeout(30);
            $this->assertSame($failure === '' ? 0 : 1, $child->run());
            $commands = json_decode(file_get_contents($receipt), true, 512, JSON_THROW_ON_ERROR);
            $names = array_map(fn ($command) => $command[1] === 'artisan' ? $command[2] : $command[1], $commands);
            $this->assertSame($failure === '' ? $expected : array_slice($expected, 0, array_search($failure, $expected, true) + 1), $names);
            if ($failure !== 'events:prepare') {
                $this->assertSame([PHP_BINARY, 'artisan', 'events:prepare', '--hosted'], $commands[10]);
            }
        }
    }
}
