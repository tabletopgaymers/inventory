<?php

use App\Support\BaselineProbe;
use App\Support\PurchaseFulfillment;
use App\Support\RequestValues;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->loadEnvironmentFrom('.env.testing');
    $app->make(Kernel::class)->bootstrap();
    config(['logging.default' => 'null']);
    $db = DB::connection();
    if (! app()->environment('testing') || $db->getDatabaseName() !== 'tg_inventory_test' || ! app(BaselineProbe::class)->inspect()['ready']) {
        throw new RuntimeException('Isolated identity');
    }
    $actor = (int) $argv[1];
    if (($argv[5] ?? '') === 'hold') {
        $db->beginTransaction();
        RequestValues::actor($actor);
        echo 'READY'.PHP_EOL;
        flush();
        $gate = dirname(__DIR__).'/_private/bison-22/concurrency-gate-'.($argv[6] ?? '');
        $deadline = microtime(true) + 15;
        while (! is_file($gate) && microtime(true) < $deadline) {
            usleep(20000);
        }
        if (! is_file($gate)) {
            throw new RuntimeException('Concurrent peer did not reach posting');
        }
        usleep(300000);
    }
    if (($argv[5] ?? '') === 'peer') {
        file_put_contents(dirname(__DIR__).'/_private/bison-22/concurrency-gate-'.($argv[6] ?? ''), 'peer posting attempt');
        echo 'ATTEMPT'.PHP_EOL;
        flush();
    }
    $data = json_decode($argv[4], true, flags: JSON_THROW_ON_ERROR);
    try {
        app(PurchaseFulfillment::class)->work($actor, (int) $argv[2], 2, $data, 'receipt', 'confirm', $argv[3]);
        echo 'POSTED'.PHP_EOL;
    } catch (HttpException $error) {
        if ($error->getStatusCode() !== 409) {
            throw $error;
        }
        echo 'CONFLICT'.PHP_EOL;
    }
    if ($db->transactionLevel() > 0) {
        $db->commit();
    }
} catch (Throwable $error) {
    file_put_contents(dirname(__DIR__).'/_private/bison-22/worker-'.getmypid().'.failure', $error->__toString());
    fwrite(STDERR, 'BLOCKED: isolated fulfillment worker.'.PHP_EOL);
    exit(1);
}
