<?php

namespace App\Console\Commands;

use Database\Seeders\DevelopmentSampleSeeder;
use Illuminate\Console\Command;
use Throwable;

class SeedDevelopmentSamples extends Command
{
    protected $signature = 'samples:seed {--actor= : Existing enabled Admin or Manager ID; defaults to the existing initial administrator} {--hosted : Explicitly permit the approved hosted development baseline} {--refresh : Explicitly reset approved local sample quantities; retain costs and history}';

    protected $description = 'Install approved development samples once; ordinary reruns preserve later edits';

    public function handle(): int
    {
        $actor = $this->option('actor');
        if (($actor !== null && ! preg_match('/\A[1-9]\d*\z/D', (string) $actor))
            || ($this->option('refresh') && ! app()->environment('local'))) {
            $this->error('Use an existing authorized actor ID or the initial administrator. Explicit refresh is restricted to local development.');

            return self::FAILURE;
        }
        try {
            app(DevelopmentSampleSeeder::class)->run($actor === null ? null : (int) $actor, (bool) $this->option('refresh'), (bool) $this->option('hosted'));
            $this->info('Development sample installation present. Existing edits are retained unless explicit local refresh was requested.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Development sample seed blocked or rolled back; inspect guards, actor and catalog identities. Private diagnostics withheld.');

            return self::FAILURE;
        }
    }
}
