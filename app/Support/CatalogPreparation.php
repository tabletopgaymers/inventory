<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class CatalogPreparation
{
    /**
     * Verify Catalog preparation for the legacy command name. Apply schema changes with the ordered application migrations. The hosted flag is required for development.
     *
     * Scope: Domain-specific (Setup and readiness)
     * Task: Prepare catalog schema
     * @example routes/console.php
     */
    public function prepare(bool $hosted): bool
    {
        $probe = app(BaselineProbe::class);
        if (! $probe->inspect()['ready'] || (app()->environment('development') && ! $hosted)) {
            return false;
        }
        return $probe->schemaState(DB::connection())['catalogReady'];
    }
}
