<?php

use Database\Migrations\Support\CatalogOwnerMetadata;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/support/CatalogOwnerMetadata.php';

return new class extends Migration
{
    public function up(): void
    {
        CatalogOwnerMetadata::expand(DB::connection());
    }

    public function down(): void
    {
        throw new LogicException('Retain Catalog owner columns. Inspect migration progress and use a verified forward repair.');
    }
};
