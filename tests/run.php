<?php

declare(strict_types=1);

$tests = [
    __DIR__ . '/validate_structure.php'
];

foreach ($tests as $test) {
    echo 'Running ' . basename($test) . "...\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($test), $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}

$process = proc_open(['python', __DIR__ . '/library_metadata.py'], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__));
if (!is_resource($process) || proc_close($process) !== 0) {
    exit(1);
}

echo "All Symcon_ModuleCI tests passed.\n";
