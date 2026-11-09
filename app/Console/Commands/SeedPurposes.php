<?php

namespace App\Console\Commands;

use Database\Seeders\PurposeSeeder;
use Illuminate\Console\Command;
use LogicException;
use Throwable;

class SeedPurposes extends Command
{
    protected $signature = 'purposes:seed {--file= : UTF-8 CSV; defaults to database/fixtures/purposes.csv}';

    protected $description = 'Create purposes and update supplied descriptions and examples';

    public function handle(): int
    {
        $this->info('Starting purpose seed...');
        try {
            $seeder = app(PurposeSeeder::class);
            $seeder->setCommand($this);
            $seeder->run($this->option('file') ?: null);
        } catch (Throwable $exception) {
            $reason = $exception instanceof LogicException
                ? $exception->getMessage()
                : 'Check the database connection, migrations and application logs. Private error details omitted.';
            if (! $exception instanceof LogicException) {
                report($exception);
            }
            $this->error('FAILED: Purpose seed did not complete. No purpose changes committed. '.$reason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
