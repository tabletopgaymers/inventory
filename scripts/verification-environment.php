<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Read-only disposable schema identity. Never exports credentials or business rows.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
require __DIR__.'/verification.php';
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->loadEnvironmentFrom('.env.testing');
    $app->make(Kernel::class)->bootstrap();
    $connection = DB::connection();
    if (! $app->environment('testing') || $connection->getDatabaseName() !== 'tg_inventory_test'
        || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('Disposable identity mismatch');
    }
    $schema = [];
    foreach (['columns' => ['TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, DATETIME_PRECISION, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_TYPE, COLUMN_KEY, EXTRA, GENERATION_EXPRESSION', 'TABLE_NAME, ORDINAL_POSITION'],
        'statistics' => ['TABLE_NAME, NON_UNIQUE, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, COLLATION, SUB_PART, NULLABLE, INDEX_TYPE', 'TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'],
        'table_constraints' => ['TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE', 'TABLE_NAME, CONSTRAINT_NAME'],
        'key_column_usage' => ['TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION, POSITION_IN_UNIQUE_CONSTRAINT, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME', 'TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION'],
        'tables' => ['TABLE_NAME, ENGINE, TABLE_COLLATION', 'TABLE_NAME']] as $table => [$columns, $order]) {
        // Identifiers are a fixed source allowlist, values are bound.
        $schema[$table] = $connection->select('SELECT '.$columns.' FROM information_schema.'.$table.' WHERE TABLE_SCHEMA = ? ORDER BY '.$order, ['tg_inventory_test']);
    }
    $schema['checks'] = $connection->select('SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.check_constraints WHERE CONSTRAINT_SCHEMA = ? ORDER BY CONSTRAINT_NAME', ['tg_inventory_test']);
    $schema['migrations'] = $connection->table('migrations')->orderBy('migration')->get(['migration', 'batch'])->all();
    $environment = ['driver' => $connection->getConfig('driver'), 'host' => $connection->getConfig('host'),
        'port' => $connection->getConfig('port'), 'database' => $connection->getDatabaseName(),
        'server_version' => $connection->selectOne('SELECT VERSION() AS version')->version];
    echo json_encode(['database' => 'tg_inventory_test', 'environment_sha256' => hash('sha256', json_encode($environment, JSON_THROW_ON_ERROR)),
        'schema_sha256' => hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR))], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    VerificationRun::blocked(dirname(__DIR__), 'disposable schema identity', $error);
    fwrite(STDERR, 'BLOCKED: disposable schema identity unavailable.'.PHP_EOL);
    exit(125);
}
