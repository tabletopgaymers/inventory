<?php

namespace App\Console\Commands;

use Database\Seeders\StartingStockSeeder;
use Illuminate\Console\Command;

class SeedStartingStock extends Command
{
    protected $signature = 'inventory:seed {--file= : UTF-8 CSV; defaults to database/seeders/fixtures/starting-stock.csv} {--actor= : Existing enabled Admin/Manager; defaults to the initial administrator}';

    protected $description = 'Set supplied inventory counts at existing locations, preserving blank cells';

    public function handle(): int
    {
        $actor = $this->option('actor');
        if ($actor !== null && ! preg_match('/\A[1-9][0-9]*\z/D', $actor)) {
            $this->error('Actor must be an existing enabled Admin/Manager ID.');

            return self::FAILURE;
        }
        $summary = app(StartingStockSeeder::class)->run($this->option('file') ?: null, $actor === null ? null : (int) $actor);
        $this->info('Starting stock: '.json_encode($summary, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
