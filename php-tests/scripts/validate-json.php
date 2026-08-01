<?php

declare(strict_types=1);

$root = $argv[1] ?? getcwd();
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "The JSON validation root does not exist.\n");
    exit(1);
}

$ignoredDirectories = [
    '.git',
    '.idea',
    '.vscode',
    'build',
    'dist',
    'node_modules',
    'vendor'
];
$count = 0;
$errors = [];

$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filter = new RecursiveCallbackFilterIterator(
    $directory,
    static function (SplFileInfo $file) use ($ignoredDirectories): bool
    {
        if (!$file->isDir()) {
            return true;
        }

        return !in_array($file->getFilename(), $ignoredDirectories, true);
    }
);
$iterator = new RecursiveIteratorIterator($filter);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'json') {
        continue;
    }

    ++$count;
    $path = $file->getPathname();
    $contents = file_get_contents($path);
    if ($contents === false) {
        $errors[] = $path . ': cannot be read';
        continue;
    }

    try {
        json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $errors[] = $path . ': ' . $exception->getMessage();
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Invalid JSON file(s):\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo 'JSON validation passed for ' . $count . " file(s).\n";
