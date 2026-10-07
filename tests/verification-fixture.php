<?php

// Cheap runner fixtures; no application bootstrap or database access.
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/scripts/verification.php';
$mode = $argv[1] ?? '';
if (! in_array($mode, ['pass', 'fail', 'timeout', 'interrupt', 'incomplete', 'source-drift', 'config-drift'], true)) {
    exit(125);
}
try {
    $run = new VerificationRun($argv[3] ?? dirname(__DIR__), 'fixture', $mode === 'incomplete' ? 2 : 1);
} catch (Throwable $error) {
    VerificationRun::blocked(dirname(__DIR__), 'fixture prerequisites', $error);
    exit(125);
}
$code = match ($mode) {
    'pass', 'incomplete' => 'echo "PRIVATE_FIXTURE_VALUE"; exit(0);',
    'fail' => 'fwrite(STDERR, "PRIVATE_FIXTURE_VALUE"); exit(7);',
    'source-drift', 'config-drift' => 'file_put_contents($argv[1], "controlled drift", FILE_APPEND);',
    default => 'file_put_contents($argv[1], (string) getmypid()); usleep(10000000);',
};
$command = [PHP_BINARY, '-r', $code];
if (in_array($mode, ['timeout', 'interrupt', 'source-drift', 'config-drift'], true)) {
    $command[] = $argv[2];
}
$result = $run->stage('controlled '.$mode, $command, $mode === 'timeout' ? 0.2 : 30);
$result = $run->finish($result);
exit($result);
