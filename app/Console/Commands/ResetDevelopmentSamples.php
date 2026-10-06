<?php

namespace App\Console\Commands;

use App\Support\DevelopmentSampleReset;
use Illuminate\Console\Command;
use Throwable;

class ResetDevelopmentSamples extends Command
{
    protected $signature = 'samples:reset {--hosted : Explicitly permit approved hosted development} {--actor= : Existing enabled Admin ID; defaults to initial administrator} {--confirm= : Must equal development-business-data}';

    protected $description = 'Explicit approved D-220/D-221 development business reset/reseed; never called by ordinary deployments';

    public function handle(): int
    {
        $actor = $this->option('actor');
        if ($actor !== null && ! preg_match('/\A[1-9]\d*\z/D', (string) $actor)) {
            $this->error('Use an existing authorized Admin actor.');

            return self::FAILURE;
        }
        try {
            $operation = app(DevelopmentSampleReset::class)->reset($actor === null ? null : (int) $actor, (bool) $this->option('hosted'), (string) $this->option('confirm'));
            $this->info('Approved development business reset/reseed committed; accounts and migrations retained. Reset operation: '.$operation);

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Development reset blocked or rolled back; inspect context, confirmation, Admin actor and schema. Private diagnostics withheld.');

            return self::FAILURE;
        }
    }
}
