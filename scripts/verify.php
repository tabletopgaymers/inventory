<?php

// Explicit focused checks; full mode preserves the existing required check list.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$root = dirname(__DIR__);
chdir($root);
require_once __DIR__.'/verification.php';
if (($argv[1] ?? '') === '--full') {
    require __DIR__.'/check.php';
    exit;
}
try {
    require $root.'/vendor/autoload.php';
    $paths = array_slice($argv, 2);
    if (($argv[1] ?? '') !== '--focused' || $paths === []) {
        throw new RuntimeException('Usage: verify.php --full | --focused tests/Feature/FileTest.php ...');
    }
    foreach ($paths as $path) {
        if (! preg_match('~^tests/Feature/[A-Za-z0-9]+Test\.php$~D', $path) || ! is_file($root.'/'.$path)) {
            throw new RuntimeException('Invalid focused test path');
        }
    }
    $run = new VerificationRun($root, 'focused');
    $code = $run->stage('focused PHP tests', [PHP_BINARY, 'vendor/phpunit/phpunit/phpunit', '--fail-on-warning', '--fail-on-risky', '--fail-on-deprecation', ...$paths], 1080);
    $code = $run->finish($code);
    exit($code);
} catch (Throwable $error) {
    VerificationRun::blocked($root, 'focused prerequisites', $error);
    fwrite(STDERR, 'BLOCKED: verification runner prerequisites or arguments invalid.'.PHP_EOL);
    exit(125);
}
