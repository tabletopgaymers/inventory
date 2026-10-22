<?php

namespace App\Console\Commands;

use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class SeedCatalog extends Command
{
    protected $signature = 'catalog:seed {--file= : UTF-8 tab file; defaults to database/seeders/fixtures/catalog.tab}';

    protected $description = 'Add catalog identities without sample stock, accounts or requests';

    public function handle(): int
    {
        $this->info('Starting catalog seed...');
        try {
            $seeder = app(CatalogSeeder::class);
            $seeder->setCommand($this);
            $seeder->run($this->option('file') ?: null);
        } catch (Throwable $exception) {
            $reason = match (true) {
                $exception instanceof ValidationException => implode(' ', $exception->validator->errors()->all()),
                $exception instanceof AuthorizationException => 'The selected account is not authorized.',
                $exception instanceof LogicException => $exception->getMessage(),
                default => 'Check the database connection, migrations and application logs. Private error details omitted.',
            };
            report($exception);
            $this->error('FAILED: Catalog seed did not complete; no catalog changes committed. '.$reason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
