<?php

use Composer\InstalledVersions;
use Symfony\Component\Process\Process;

// Run from any directory: php D:\Sites\tg-inventory-app\scripts\check.php
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$stage = 'dependencies';
try {
    $root = dirname(__DIR__);
    chdir($root);
    if (! is_file($root.'/vendor/autoload.php') || ! is_file($root.'/composer.lock')) {
        throw new RuntimeException('Dependencies missing');
    }
    if (is_file($root.'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Clear the local configuration cache before checks');
    }
    require $root.'/vendor/autoload.php';
    $lock = json_decode(file_get_contents($root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
        if (! InstalledVersions::isInstalled($package['name'])
            || InstalledVersions::getPrettyVersion($package['name']) !== $package['version']) {
            throw new RuntimeException('Dependencies differ from lockfile');
        }
    }
    $stage = 'static baseline assets';
    foreach (['public/index.php', 'public/baseline.css', 'resources/views/welcome.blade.php'] as $asset) {
        if (! is_file($root.'/'.$asset) || filesize($root.'/'.$asset) === 0) {
            throw new RuntimeException('Baseline asset missing');
        }
    }
    echo 'PASS: static baseline assets'.PHP_EOL;
    $checks = [
        'local database and configuration' => [PHP_BINARY, 'artisan', 'baseline:check', '--no-ansi'],
        'isolated test database and configuration' => [PHP_BINARY, 'artisan', 'baseline:check', '--env=testing', '--no-ansi'],
        'local inventory schema' => [PHP_BINARY, 'artisan', 'inventory:check', '--no-ansi'],
        'isolated test inventory schema' => [PHP_BINARY, 'artisan', 'inventory:check', '--env=testing', '--no-ansi'],
        'local catalog schema' => [PHP_BINARY, 'artisan', 'catalog:check', '--no-ansi'],
        'isolated test catalog schema' => [PHP_BINARY, 'artisan', 'catalog:check', '--env=testing', '--no-ansi'],
        'local count and cost schema' => [PHP_BINARY, 'artisan', 'daily-inventory:check', '--no-ansi'],
        'isolated test count and cost schema' => [PHP_BINARY, 'artisan', 'daily-inventory:check', '--env=testing', '--no-ansi'],
        'PHP formatting' => [PHP_BINARY, 'vendor/laravel/pint/builds/pint', '--test'],
        'database integration and failure handling' => [PHP_BINARY, 'vendor/phpunit/phpunit/phpunit', '--fail-on-warning', '--fail-on-risky', '--fail-on-deprecation'],
    ];
    foreach ($checks as $stage => $command) {
        $process = new Process($command, $root);
        $process->setTimeout($stage === 'database integration and failure handling' ? 900 : 120);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Check failed');
        }
        echo 'PASS: '.$stage.PHP_EOL;
    }
    echo 'Local baseline checks passed. Browser HTTPS verification is separate.'.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'FAIL: '.$stage.'. Check the documented local setup; private diagnostics withheld.'.PHP_EOL);
    exit(1);
}
