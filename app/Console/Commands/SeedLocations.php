<?php

namespace App\Console\Commands;

use Database\Seeders\LocationSeeder;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class SeedLocations extends Command
{
    protected $signature = 'locations:seed {--file= : UTF-8 CSV; defaults to database/fixtures/locations.csv}';

    protected $description = 'Create locations and update supplied mailing details without changing stock or the Central assignment';

    public function handle(): int
    {
        $this->info('Starting location seed...');
        try {
            $seeder = app(LocationSeeder::class);
            $seeder->setCommand($this);
            $seeder->run($this->option('file') ?: null);
        } catch (Throwable $exception) {
            $reason = match (true) {
                $exception instanceof ValidationException => implode(' ', $exception->validator->errors()->all()),
                $exception instanceof LogicException => $exception->getMessage(),
                default => 'Check the database connection, migrations and application logs. Private error details omitted.',
            };
            report($exception);
            $this->error('FAILED: Location seed did not complete; no location changes committed. '.$reason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
