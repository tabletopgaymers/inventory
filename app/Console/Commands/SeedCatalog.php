<?php

namespace App\Console\Commands;

use Database\Seeders\CatalogSeeder;
use Illuminate\Console\Command;

class SeedCatalog extends Command
{
    protected $signature = 'catalog:seed {--file= : UTF-8 tab file; defaults to database/seeders/fixtures/catalog.tab}';

    protected $description = 'Add catalog identities without sample stock, accounts or requests';

    public function handle(): int
    {
        $seeder = app(CatalogSeeder::class);
        $seeder->setCommand($this);
        $seeder->run($this->option('file') ?: null);

        return self::SUCCESS;
    }
}
