<?php

use App\Support\ReleaseStatus;
use Symfony\Component\Process\Process;

// Invoked only in the candidate directory before Forge activates the release.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $root = dirname(__DIR__);
    $expected = getenv('FORGE_VAR_CHECKED_SHA');
    if (! is_string($expected) || ! preg_match('/\A[0-9a-f]{40}\z/D', $expected)) {
        throw new RuntimeException;
    }
    $head = new Process(['git', 'rev-parse', 'HEAD'], $root);
    $head->mustRun();
    $status = new Process(['git', 'status', '--porcelain', '-z', '--untracked-files=all'], $root);
    $status->mustRun();
    if (trim($head->getOutput()) !== $expected) {
        throw new RuntimeException;
    }
    $target = realpath($root.'/storage');
    $release = realpath($root);
    $sharedStorage = is_link($root.'/storage') && is_dir($root.'/storage')
        && $target !== false && $release !== false && $target !== $release
        && ! str_starts_with($target, $release.DIRECTORY_SEPARATOR);
    if (! ReleaseStatus::acceptable($status->getOutput(), $sharedStorage)) {
        throw new RuntimeException;
    }
    if (file_put_contents($root.'/bootstrap/cache/revision', $expected."\n", LOCK_EX) === false) {
        throw new RuntimeException;
    }
    echo 'PASS: candidate matches the CI-checked revision '.$expected.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'FAIL: candidate revision gate; activation must stop.'.PHP_EOL);
    exit(1);
}
