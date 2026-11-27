<?php

use Database\Migrations\Support\CatalogOwnerMetadata;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

require_once __DIR__.'/support/CatalogOwnerMetadata.php';

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection();
        $schema = $db->getSchemaBuilder();
        if (! $schema->hasTable('catalog_metadata')) {
            throw new LogicException('Legacy Catalog metadata is absent before cutover. Inspect the migration ledger and verified recovery point before repairing.');
        }
        CatalogOwnerMetadata::assertExpanded($db);

        // The release must keep all legacy and owner writers stopped until this migration completes.
        $counts = CatalogOwnerMetadata::refresh($db);
        Log::info('Catalog owner cutover verified before legacy table removal.', $counts);
        $schema->drop('catalog_metadata');
        if ($schema->hasTable('catalog_metadata')) {
            throw new LogicException('Legacy Catalog metadata still exists after removal. Keep the release closed for inspection.');
        }
    }

    public function down(): void
    {
        throw new LogicException('Catalog metadata removal cannot be reversed automatically. Use verified database recovery or forward repair.');
    }
};
