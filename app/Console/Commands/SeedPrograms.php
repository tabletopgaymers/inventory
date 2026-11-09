<?php

namespace App\Console\Commands;

use Database\Seeders\ProgramSeeder;
use Illuminate\Console\Command;
use LogicException;
use Throwable;

class SeedPrograms extends Command
{
    protected $signature = 'programs:seed {--file= : UTF-8 CSV; defaults to database/fixtures/programs.csv}';

    protected $description = 'Create programs and update supplied descriptions';

    public function handle(): int
    {
        $this->info('Starting program seed...');
        try {
            $seeder = app(ProgramSeeder::class);
            $seeder->setCommand($this);
            $seeder->run($this->option('file') ?: null);
        } catch (Throwable $exception) {
            $reason = $exception instanceof LogicException
                ? $exception->getMessage()
                : 'Check the database connection, migrations and application logs. Private error details omitted.';
            if (! $exception instanceof LogicException) {
                report($exception);
            }
            $this->error('FAILED: Program seed did not complete. No program changes committed. '.$reason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
