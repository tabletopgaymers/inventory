<?php

use App\Support\BaselineProbe;
use App\Support\EventWorkflow;
use App\Support\RequestValues;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
$directory = dirname(__DIR__).'/_private/event-concurrency';
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->loadEnvironmentFrom('.env.testing');
    $app->make(Kernel::class)->bootstrap();
    config(['logging.default' => 'null']);
    $db = DB::connection();
    if (! app()->environment('testing') || $db->getDatabaseName() !== 'tg_inventory_test' || $db->getConfig('username') !== 'tg_inventory_test' || ! app(BaselineProbe::class)->inspect()['ready'] || ! app(BaselineProbe::class)->schemaState($db)['eventsReady']) {
        throw new RuntimeException('Exact isolated identity');
    }
    $actor = (int) $argv[1];
    $id = (int) $argv[2];
    $revision = (int) $argv[3];
    $mode = $argv[4];
    $token = $argv[5];
    $side = $argv[7];
    $gateId = $argv[8];
    if (! in_array($mode, ['counts', 'finalize'], true) || ! in_array($side, ['hold', 'peer'], true) || ! Str::isUuid($gateId)) {
        throw new RuntimeException('Bounded worker mode');
    }
    if (! is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    $gate = $directory.'/gate-'.$gateId;
    if ($side === 'hold') {
        $db->beginTransaction();
        RequestValues::actor($actor);
        echo 'READY'.PHP_EOL;
        flush();
        $deadline = microtime(true) + 15;
        while (! is_file($gate) && microtime(true) < $deadline) {
            usleep(20000);
        }
        if (! is_file($gate)) {
            throw new RuntimeException('Peer gate absent');
        }
        usleep(250000);
    } else {
        file_put_contents($gate, 'peer posting attempt');
        echo 'ATTEMPT'.PHP_EOL;
        flush();
    }
    try {
        app(EventWorkflow::class)->work($actor, $id, $revision, $mode, json_decode($argv[6], true, flags: JSON_THROW_ON_ERROR), $mode === 'counts' ? 'save' : 'confirm', $token);
        echo 'POSTED'.PHP_EOL;
    } catch (ValidationException) {
        echo 'CONFLICT'.PHP_EOL;
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
    if (isset($db)) {
        while ($db->transactionLevel() > 0) {
            $db->rollBack();
        }
    }
    if (! is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    file_put_contents($directory.'/worker-'.getmypid().'.failure', $error->__toString());
    fwrite(STDERR, 'BLOCKED isolated event worker.'.PHP_EOL);
    exit(1);
}
