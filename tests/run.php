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

echo "All Symcon_ModuleCI tests passed.\n";
