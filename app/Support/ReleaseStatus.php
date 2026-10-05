<?php

namespace App\Support;

class ReleaseStatus
{
    public static function acceptable(string $status, bool $externalStorageLink): bool
    {
        // Only the ten tracked runtime placeholders may disappear behind Forge's
        // storage link. Staged edits and all other source/untracked changes fail.
        $placeholders = [
            'storage/app/.gitignore',
            'storage/app/private/.gitignore',
            'storage/app/public/.gitignore',
            'storage/framework/.gitignore',
            'storage/framework/cache/.gitignore',
            'storage/framework/cache/data/.gitignore',
            'storage/framework/sessions/.gitignore',
            'storage/framework/testing/.gitignore',
            'storage/framework/views/.gitignore',
            'storage/logs/.gitignore',
        ];
        foreach (explode("\0", $status) as $entry) {
            if ($entry === '') {
                continue;
            }
            $code = substr($entry, 0, 2);
            $path = substr($entry, 3);
            if (! $externalStorageLink || ! (($code === ' D' && in_array($path, $placeholders, true))
                || ($code === '??' && $path === 'storage'))) {
                return false;
            }
        }

        return true;
    }
}
