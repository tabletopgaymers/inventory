<?php

use App\Support\StockPosting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
putenv('APP_ENV=testing');
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (! $app->environment('testing') || $db->getDatabaseName() !== 'tg_inventory_test') {
    exit(2);
}
try {
    $rows = json_decode($argv[4], true, flags: JSON_THROW_ON_ERROR);
    if (($argv[5] ?? '') === 'hold') {
        $db->beginTransaction();
        $db->table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
        echo 'READY'.PHP_EOL;
        flush();
        usleep(600000);
    }
    $id = app(StockPosting::class)->post((int) $argv[1], (int) $argv[2], $argv[3], $rows);
    if ($db->transactionLevel()) {
        $db->commit();
    }
    echo 'POSTED '.$id.PHP_EOL;
} catch (Throwable) {
    while ($db->transactionLevel()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Isolated stock worker failed; private diagnostics withheld.'.PHP_EOL);
    exit(1);
}
