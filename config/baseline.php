<?php

$revisionFile = dirname(__DIR__).'/bootstrap/cache/revision';
$revision = is_file($revisionFile) ? trim((string) file_get_contents($revisionFile)) : '';

return [
    'revision' => preg_match('/\A[0-9a-f]{40}\z/D', $revision) ? $revision : null,
];
