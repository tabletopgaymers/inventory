<?php

namespace App\Support;

use Symfony\Component\Process\Process;
use Throwable;

class ReleaseMetadata
{
    public function current(): array
    {
        $preparationId = config('baseline.preparation_id');
        $recorded = [
            'branch' => (string) config('baseline.branch'),
            'revision' => (string) config('baseline.revision'),
            'modified' => false,
            'preparation_id' => app()->environment(['stage', 'staging']) && is_string($preparationId)
                && preg_match('/\A[a-f0-9]{32}\z/', $preparationId) ? $preparationId : null,
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
                'preparation_id' => null,
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
