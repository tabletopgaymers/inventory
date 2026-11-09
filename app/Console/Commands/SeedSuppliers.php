<?php

namespace App\Console\Commands;

use Database\Seeders\SupplierSeeder;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class SeedSuppliers extends Command
{
    protected $signature = 'suppliers:seed {--file= : UTF-8 CSV; defaults to database/fixtures/suppliers.csv}';

    protected $description = 'Create suppliers and update supplied contact details';

    public function handle(): int
    {
        $this->info('Starting supplier seed...');
        try {
            $seeder = app(SupplierSeeder::class);
            $seeder->setCommand($this);
            $seeder->run($this->option('file') ?: null);
        } catch (Throwable $exception) {
            $reason = match (true) {
                $exception instanceof ValidationException => implode(' ', $exception->validator->errors()->all()),
                $exception instanceof LogicException => $exception->getMessage(),
                default => 'Check the database connection, migrations and application logs. Private error details omitted.',
            };
            report($exception);
            $this->error('FAILED: Supplier seed did not complete. No supplier changes committed. '.$reason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
