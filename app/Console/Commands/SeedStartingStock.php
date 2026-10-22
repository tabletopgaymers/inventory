<?php

namespace App\Console\Commands;

use Database\Seeders\StartingStockSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class SeedStartingStock extends Command
{
    protected $signature = 'inventory:seed {--file= : UTF-8 CSV; defaults to database/seeders/fixtures/starting-stock.csv} {--actor= : Existing enabled Admin/Manager; defaults to the initial administrator}';

    protected $description = 'Set supplied inventory counts at existing locations, preserving blank cells';

    public function handle(): int
    {
        $actor = $this->option('actor');
        if ($actor !== null && ! preg_match('/\A[1-9][0-9]*\z/D', $actor)) {
            $this->error('FAILED: Starting-stock seed did not run. Actor must be an existing enabled Admin/Manager ID.');

            return self::FAILURE;
        }
        $this->info('Starting stock import...');
        try {
            $seeder = app(StartingStockSeeder::class);
            $seeder->setCommand($this);
            $seeder->run($this->option('file') ?: null, $actor === null ? null : (int) $actor);
        } catch (Throwable $exception) {
            $reason = match (true) {
                $exception instanceof ValidationException => implode(' ', $exception->validator->errors()->all()),
                $exception instanceof AuthorizationException => 'The selected account must be an existing enabled Admin/Manager.',
                $exception instanceof LogicException => $exception->getMessage(),
                default => 'Check the database connection, migrations and application logs. Private error details omitted.',
            };
            report($exception);
            $this->error('FAILED: Starting-stock seed did not complete; no stock changes committed. '.$reason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
