<?php

use Symfony\Component\Process\Process;

// Run in the checked candidate after shared .env/storage linkage, before activation.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$stage = 'checked revision';
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $root = dirname(__DIR__);
    $commands = [
        'checked revision' => [PHP_BINARY, 'scripts/release-gate.php'],
        'fresh configuration' => [PHP_BINARY, 'artisan', 'config:clear'],
        'isolated baseline' => [PHP_BINARY, 'artisan', 'baseline:check'],
        'private provider configuration' => [PHP_BINARY, 'artisan', 'authentication:configuration'],
        'forward authentication migration' => [PHP_BINARY, 'artisan', 'authentication:prepare', '--hosted'],
        'forward inventory migration' => [PHP_BINARY, 'artisan', 'inventory:prepare', '--hosted'],
        'forward catalog migration' => [PHP_BINARY, 'artisan', 'catalog:prepare', '--hosted'],
        'forward count and cost migration' => [PHP_BINARY, 'artisan', 'daily-inventory:prepare', '--hosted'],
        'forward request intake migration' => [PHP_BINARY, 'artisan', 'requests:prepare', '--hosted'],
        'configuration cache' => [PHP_BINARY, 'artisan', 'config:cache'],
        'authentication readiness' => [PHP_BINARY, 'artisan', 'authentication:check'],
        'inventory readiness' => [PHP_BINARY, 'artisan', 'inventory:check'],
        'catalog readiness' => [PHP_BINARY, 'artisan', 'catalog:check'],
        'count and cost readiness' => [PHP_BINARY, 'artisan', 'daily-inventory:check'],
        'request intake readiness' => [PHP_BINARY, 'artisan', 'requests:check'],
        'approved development samples' => [PHP_BINARY, 'artisan', 'samples:seed', '--hosted'],
        'views' => [PHP_BINARY, 'artisan', 'view:cache'],
    ];
    foreach ($commands as $stage => $command) {
        $process = new Process($command, $root);
        $process->setTimeout(120);
        $process->mustRun();
        echo 'PASS: '.$stage.PHP_EOL;
    }
} catch (Throwable) {
    fwrite(STDERR, 'FAIL: '.$stage.'; activation must stop. Private diagnostics withheld.'.PHP_EOL);
    exit(1);
}
