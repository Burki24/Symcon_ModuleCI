<?php

declare(strict_types=1);

require __DIR__ . '/../php-tests/scripts/check-encoding.php';
$check = static function (bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$valid = "Deutsch: ä ö ü Ä Ö Ü ß. Português: Ã. Français: été. 東京.\n";
$check(ProjectEncodingCheck::Inspect($valid) === [], 'Correct UTF-8 text must pass');
$broken = ["\u{00C3}\u{00A4}", "\u{00C3}\u{00B6}", "\u{00C3}\u{00BC}", "\u{00C3}\u{201E}", "\u{00C3}\u{2013}", "\u{00C3}\u{0153}", "\u{00C3}\u{0178}",
    "\u{00C3}\u{0084}", "\u{00C3}\u{0096}", "\u{00C3}\u{009C}", "\u{00C3}\u{009F}",
    "\u{00C3}\u{0192}\u{00C2}\u{00A4}", "\u{00C3}\u{0083}\u{00C2}\u{00BC}", "\u{00C3}\u{0192}\u{00C5}\u{201C}", "\u{FFFD}", "\xE4"];
foreach ($broken as $text) {
    $errors = ProjectEncodingCheck::Inspect("Valid first line\n" . $text);
    $check(count($errors) === 1 && $errors[0]['line'] === 2, 'Damaged input must be detected at its real line');
}
$root = sys_get_temp_dir() . '/z2ma-encoding-' . bin2hex(random_bytes(8));
mkdir($root);
$write = static function (string $relative, string $text) use ($root): void
{
    $path = $root . '/' . $relative;
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $text);
};
$resolvedRoot = realpath($root);
$check($resolvedRoot !== false && dirname($resolvedRoot) === realpath(sys_get_temp_dir()), 'Fixture cleanup must stay in the temporary directory');
$remove = static function (string $directory) use (&$remove, $resolvedRoot): void
{
    $resolved = realpath($directory);
    if ($resolved === false || ($resolved !== $resolvedRoot && !str_starts_with($resolved, $resolvedRoot . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Fixture cleanup escaped its temporary root');
    }
    foreach (scandir($directory) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . '/' . $entry;
        if (is_dir($path) && !is_link($path)) {
            $remove($path);
        } else {
            unlink($path);
        }
    }
    rmdir($directory);
};
try {
    foreach (['.git', '.style', 'tests/stubs', 'libs/helper', 'libs/vendor', 'vendor', 'node_modules', '.preview', '.idea', '.vscode'] as $excluded) {
        $write($excluded . '/upstream.php', $broken[0]);
    }
    $write('image.png', "\x89PNG\0" . $broken[0]);
    $write('.env', $broken[0]);
    $write('README.md', $valid);
    $write('AGENTS.md', $valid);
    $check(ProjectEncodingCheck::Scan($root) === [], 'Valid project files, binary assets and excluded sources must pass');
    foreach (['README.md', 'Z2MAutomations/module.php', 'Z2MAutomations/locale.json', 'libs/z2m/src/extension.mjs', 'tests/mqtt.php', '.github/workflows/tests.yml', '.editorconfig'] as $file) {
        $write($file, "OK\n" . $broken[0]);
        $errors = ProjectEncodingCheck::Scan($root);
        $check(count($errors) === 1 && str_starts_with($errors[0], $file . ':2:'), 'Own files must fail with a relative path and line');
        $check(file_get_contents($root . '/' . $file) === "OK\n" . $broken[0], 'The checker must never rewrite a file');
        $write($file, $valid);
    }
    $check(file_get_contents($root . '/libs/helper/upstream.php') === $broken[0], 'Upstream content must remain unchanged');
    $write('tests/check_encoding.php', file_get_contents(__DIR__ . '/../php-tests/scripts/check-encoding.php'));
    $run = static function () use ($root): array
    {
        $process = proc_open([PHP_BINARY, $root . '/tests/check_encoding.php', $root], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $root);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start encoding checker fixture');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    };
    $write('README.md', "OK\n" . $broken[0]);
    [$exitCode, $output] = $run();
    $check($exitCode === 1 && str_contains($output, 'README.md:2:'), 'Real CLI invocation must fail with path and line for damaged project text');
    $write('README.md', $valid);
    [$exitCode, $output] = $run();
    $check($exitCode === 0 && str_contains($output, 'Project text encoding passed.'), 'Real CLI invocation must pass valid text while ignoring upstream errors');

} finally {
    $remove($root);
}
echo "Encoding checker regression cases passed.\n";
