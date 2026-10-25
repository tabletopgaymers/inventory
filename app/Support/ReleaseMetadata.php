<?php

namespace App\Support;

use Symfony\Component\Process\Process;
use Throwable;

class ReleaseMetadata
{
    public function current(): array
    {
        $recorded = [
            'branch' => (string) config('baseline.branch'),
            'revision' => (string) config('baseline.revision'),
            'modified' => false,
        ];

        if (! app()->environment('local') || ! file_exists(base_path('.git'))) {
            return $recorded;
        }

        try {
            $revision = $this->git(['rev-parse', '--verify', 'HEAD']);
            $branch = $this->git(['branch', '--show-current']);
            $status = $this->git(['status', '--porcelain=v1', '--untracked-files=normal']);

            return [
                'branch' => $branch !== '' ? $branch : 'Detached HEAD',
                'revision' => $revision,
                'modified' => $status !== '',
            ];
        } catch (Throwable) {
            // Optional local version information must never prevent page rendering.
            return $recorded;
        }
    }

    private function git(array $arguments): string
    {
        $process = new Process(['git', '--no-optional-locks', '-C', base_path(), ...$arguments]);
        $process->setTimeout(2);
        $process->mustRun();

        return rtrim($process->getOutput(), "\r\n");
    }
}
