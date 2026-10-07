<?php

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/** Private diagnostics supplement checks; receipts never confer review or approval. */
final class VerificationRun
{
    public static function blocked(string $root, string $stage, Throwable $error): void
    {
        if (count(glob($root.'/_private/verification/*', GLOB_ONLYDIR) ?: []) >= 100) {
            fwrite(STDERR, 'Private report capacity reached; inspect and archive owned reports.'.PHP_EOL);

            return;
        }
        $directory = $root.'/_private/verification/blocked-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6));
        if (! mkdir($directory, 0700, true)) {
            return;
        }
        file_put_contents($directory.'/failure.log', $error->__toString());
        chmod($directory.'/failure.log', 0600);
        file_put_contents($directory.'/result.json', json_encode(['status' => 'runner-error', 'stage' => $stage, 'exit_code' => 125], JSON_THROW_ON_ERROR));
        chmod($directory.'/result.json', 0600);
        fwrite(STDERR, 'Private prerequisite receipt: '.$directory.'/result.json'.PHP_EOL);
    }

    private array $receipt;

    private string $directory;

    private $lease;

    private ?Process $active = null;

    private bool $finished = false;

    public function __construct(private string $root, private string $mode, private int $expectedStages = 1)
    {
        $reports = glob($root.'/_private/verification/*', GLOB_ONLYDIR) ?: [];
        if (count($reports) >= 100) {
            throw new RuntimeException('Private report retention limit reached; inspect and archive owned reports');
        }
        $this->directory = $root.'/_private/verification/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6));
        if (! mkdir($this->directory, 0700, true)) {
            throw new RuntimeException('Private report directory unavailable');
        }
        $this->receipt = ['version' => 1, 'mode' => $mode, 'status' => 'interrupted', 'started_utc' => gmdate('c'),
            'required_stages' => $expectedStages, 'environment' => ['php' => PHP_VERSION, 'os' => PHP_OS_FAMILY], 'identity' => [], 'stages' => []];
        $snapshot = $this->snapshot();
        foreach ($snapshot as $key => $value) {
            $this->receipt[$key] = $value;
        }
        // Machine-wide cooperative lock: different worktrees cannot run this runner
        // against the same disposable database concurrently. Never deletes a lock.
        $this->lease = fopen(sys_get_temp_dir().'/tg-inventory-verification-'.($mode === 'fixture' ? 'fixture' : 'test').'.lock', 'c');
        if ($this->lease === false || ! flock($this->lease, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Disposable test verification lease busy');
        }
        register_shutdown_function(function () {
            if (! $this->finished) {
                if ($this->active?->isRunning()) {
                    $this->active->stop(1);
                }
                $this->save();
            }
        });
        $this->save();
    }

    public function stage(string $name, array $command, float $timeout): int
    {
        $number = count($this->receipt['stages']) + 1;
        $junit = null;
        if (in_array('vendor/phpunit/phpunit/phpunit', $command, true)) {
            $junit = $this->directory.'/stage-'.$number.'.xml';
            $command = [...$command, '--log-junit', $junit];
        }
        $record = ['name' => $name, 'command' => $command, 'timeout_seconds' => $timeout,
            'status' => 'interrupted', 'exit_code' => null, 'seconds' => null];
        $this->receipt['stages'][] = $record;
        $this->save();
        $stream = fopen($this->directory.'/stage-'.$number.'.log', 'xb');
        if ($stream === false) {
            throw new RuntimeException('Private diagnostics unavailable');
        }
        chmod($this->directory.'/stage-'.$number.'.log', 0600);
        $start = hrtime(true);
        $this->active = new Process($command, $this->root, ['VERIFICATION_PHP' => PHP_BINARY]);
        $this->active->setTimeout($timeout);
        $this->active->disableOutput();
        $bytes = 0;
        $truncated = false;
        try {
            $code = $this->active->run(static function ($type, $buffer) use ($stream, &$bytes, &$truncated) {
                $chunk = '['.$type.'] '.$buffer;
                $remaining = max(0, 4 * 1024 * 1024 - $bytes);
                if (strlen($chunk) > $remaining) {
                    $truncated = true;
                }
                $bytes += fwrite($stream, substr($chunk, 0, $remaining));
            });
            $record['status'] = $code === 0 ? 'passed' : 'failed';
        } catch (ProcessTimedOutException) {
            $this->active->stop(1);
            $code = 124;
            $record['status'] = 'timeout';
        } catch (Throwable $error) {
            fwrite($stream, $error->__toString());
            $code = 125;
            $record['status'] = 'runner-error';
        } finally {
            fclose($stream);
        }
        $record['seconds'] = round((hrtime(true) - $start) / 1e9, 3);
        $record['exit_code'] = $code;
        $record['log_truncated'] = $truncated;
        if ($junit !== null && is_file($junit)) {
            $xml = simplexml_load_file($junit);
            if ($xml !== false) {
                $coverage = ['tests' => 0, 'assertions' => 0, 'errors' => 0, 'failures' => 0, 'skipped' => 0];
                foreach ($xml->xpath('/testsuites/testsuite') as $suite) {
                    foreach ($coverage as $key => $value) {
                        $coverage[$key] += (int) $suite[$key];
                    }
                }
                $record['coverage'] = $coverage;
            }
        }
        $this->receipt['stages'][$number - 1] = $record;
        $this->active = null;
        $this->save();
        // No raw output, exception messages or inferred coverage in public summary.
        echo strtoupper($record['status']).': '.$name.' ('.$record['seconds'].'s; exit '.$code.')'.PHP_EOL;

        return $code;
    }

    public function finish(int $code): int
    {
        if ($code === 0 && (count($this->receipt['stages']) !== $this->expectedStages
            || array_filter($this->receipt['stages'], fn ($stage) => $stage['status'] !== 'passed') !== [])) {
            $code = 125;
        }
        if ($code === 0) {
            try {
                $final = $this->snapshot();
                $this->receipt['completion_identity'] = ['status' => 'checking', 'identity' => $final['identity'],
                    'raw_manifest_sha256' => $final['candidate']['raw_manifest_sha256'],
                    'normalized_manifest_sha256' => $final['candidate']['normalized_manifest_sha256'],
                    'environment' => $final['environment']];
                if ($final['candidate'] !== $this->receipt['candidate']) {
                    throw new RuntimeException('Candidate identity changed during verification');
                }
                if ($final['identity'] !== $this->receipt['identity']) {
                    throw new RuntimeException('Configuration or dependency identity changed during verification');
                }
                if ($final['environment'] !== $this->receipt['environment']) {
                    throw new RuntimeException('Environment or disposable schema changed; schema tests must restore the starting schema before PASS');
                }
                $this->receipt['completion_identity']['status'] = 'matched';
            } catch (Throwable $error) {
                $code = 125;
                $this->receipt['completion_identity']['status'] = 'mismatch-or-unavailable';
                file_put_contents($this->directory.'/completion-failure.log', $error->__toString());
                chmod($this->directory.'/completion-failure.log', 0600);
            }
        }
        $this->receipt['status'] = $code === 0 ? 'passed' : 'blocked';
        $this->receipt['exit_code'] = $code;
        $this->receipt['ended_utc'] = gmdate('c');
        $this->save();
        $this->finished = true;
        flock($this->lease, LOCK_UN);
        fclose($this->lease);
        echo 'Private receipt: '.$this->directory.'/result.json'.PHP_EOL;

        return $code;
    }

    private function snapshot(): array
    {
        $snapshot = ['identity' => [], 'environment' => ['php' => PHP_VERSION, 'os' => PHP_OS_FAMILY]];
        foreach (['composer.lock', 'phpunit.xml', '.env', '.env.testing', 'scripts/check.php', 'scripts/verification.php', 'scripts/verify.php',
            'vendor/composer/installed.json', 'vendor/composer/autoload_classmap.php'] as $path) {
            $snapshot['identity'][$path] = is_file($this->root.'/'.$path) ? hash_file('sha256', $this->root.'/'.$path) : null;
        }
        $identity = new Process(['node', 'scripts/candidate.cjs', 'receipt'], $this->root);
        $identity->setTimeout(30);
        $identity->mustRun();
        $snapshot['candidate'] = json_decode($identity->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $extensions = get_loaded_extensions();
        sort($extensions);
        $snapshot['environment']['extensions'] = $extensions;
        $ini = php_ini_loaded_file();
        $snapshot['environment']['ini_sha256'] = $ini && is_file($ini) ? hash_file('sha256', $ini) : null;
        if ($this->mode !== 'fixture') {
            $environment = new Process([PHP_BINARY, 'scripts/verification-environment.php'], $this->root);
            $environment->setTimeout(30);
            $environment->mustRun();
            $snapshot['environment']['disposable_schema'] = json_decode($environment->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $snapshot;
    }

    private function save(): void
    {
        $temporary = $this->directory.'/result.tmp';
        file_put_contents($temporary, json_encode($this->receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);
        chmod($temporary, 0600);
        rename($temporary, $this->directory.'/result.json');
    }
}
