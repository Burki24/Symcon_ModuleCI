<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$requiredFiles = [
    '.github/workflows/style.yml',
    '.github/workflows/tests.yml',
    '.gitmodules',
    '.style/.php-cs-fixer.php',
    '.style/json-check.php',
    'LICENSE',
    'README.md',
    'php-tests/action.yml',
    'php-tests/scripts/lint-php.sh',
    'php-tests/scripts/validate-json.php',
    'style/action.yml',
    'tests/run.php',
    'tests/stubs/autoload.php',
    'tests/validate_structure.php'
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = 'Missing required file: ' . $file;
    }
}

$checks = [
    'php-tests/action.yml'        => [
        'name: Symcon module tests',
        'using: composite',
        'uses: shivammathur/setup-php@v2',
        'uses: actions/setup-python@v6',
        'name: Initialize repository submodules',
        'git -C "$GITHUB_WORKSPACE" submodule update --init --recursive',
        "default: 'php tests/run.php'"
    ],
    'style/action.yml'            => [
        'name: Symcon module style',
        'using: composite',
        'uses: symcon/action-style@v3'
    ],
    '.github/workflows/tests.yml' => [
        'name: Tests',
        'tests:',
        'name: tests',
        'uses: ./php-tests'
    ],
    '.github/workflows/style.yml' => [
        'name: Check Style',
        'style:',
        'name: style',
        'uses: ./style'
    ]
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    $contents = is_file($path) ? file_get_contents($path) : false;
    if ($contents === false) {
        continue;
    }

    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $errors[] = $file . ' is missing required content: ' . $needle;
        }
    }
}

$gitmodules = file_get_contents($root . '/.gitmodules');
if ($gitmodules === false) {
    $errors[] = '.gitmodules cannot be read.';
} else {
    foreach ([
        'url = https://github.com/symcon/StylePHP',
        'url = https://github.com/symcon/SymconStubs'
    ] as $submoduleUrl) {
        if (!str_contains($gitmodules, $submoduleUrl)) {
            $errors[] = '.gitmodules is missing required content: ' . $submoduleUrl;
        }
    }
}

foreach (['php-tests/action.yml', 'style/action.yml'] as $actionFile) {
    $contents = file_get_contents($root . '/' . $actionFile);
    if ($contents !== false && str_contains($contents, "\t")) {
        $errors[] = $actionFile . ' contains tab characters.';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Structure validation failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "Symcon_ModuleCI structure is valid.\n";
