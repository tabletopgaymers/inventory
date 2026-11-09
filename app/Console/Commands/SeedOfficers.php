<?php

namespace App\Console\Commands;

use Database\Seeders\OfficerSeeder;
use Illuminate\Console\Command;
use LogicException;
use Throwable;

class SeedOfficers extends Command
{
    protected $signature = 'users:seed {--file= : UTF-8 CSV; defaults to database/fixtures/users.csv} {--actor= : Enabled Admin ID; defaults to the initial administrator}';

    protected $description = 'Import officer identities, fill blank profile fields and grant roster roles';

    public function handle(): int
    {
        $actor = $this->option('actor');
        $actorId = $actor === null ? null : filter_var($actor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($actorId === false) {
            $this->error('FAILED: Supply a positive integer user ID for --actor.');

            return self::FAILURE;
        }

        $this->info('Starting officer seed...');
        try {
            $seeder = app(OfficerSeeder::class);
            $seeder->setCommand($this);
            $seeder->run($this->option('file') ?: null, $actorId);
        } catch (Throwable $exception) {
            $reason = $exception instanceof LogicException
                ? $exception->getMessage()
                : 'Check the database connection, migrations and application logs. Private error details omitted.';
            if (! $exception instanceof LogicException) {
                report($exception);
            }
            $this->error('FAILED: Officer seed did not complete. No officer changes committed. '.$reason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
