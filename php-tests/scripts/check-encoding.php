<?php

declare(strict_types=1);

final class ProjectEncodingCheck
{
    private const EXCLUDED = ['.git', '.style', 'tests/stubs', 'libs/helper', 'libs/vendor', 'vendor', 'node_modules', '.preview', '.idea', '.vscode'];
    private const EXTENSIONS = ['php', 'md', 'json', 'mjs', 'js', 'ts', 'yaml', 'yml', 'html', 'css', 'svg', 'txt'];
    private const NAMES = ['LICENSE', '.gitattributes', '.gitignore', '.gitmodules', '.editorconfig'];

    public static function Scan(string $root): array
    {
        $errors = [];
        $walk = static function (string $relative) use ($root, &$errors, &$walk): void
        {
            $directory = $root . ($relative === '' ? '' : '/' . $relative);
            $entries = scandir($directory);
            if ($entries === false) {
                throw new RuntimeException('Cannot read project directory: ' . $relative);
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $name = $relative === '' ? $entry : $relative . '/' . $entry;
                $path = $root . '/' . $name;
                // Do not inspect linked sources outside the project, or immutable upstream bundles.
                if (is_link($path) || in_array($name, self::EXCLUDED, true)) {
                    continue;
                }
                if (is_dir($path)) {
                    $walk($name);
                    continue;
                }
                if (!in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), self::EXTENSIONS, true) && !in_array($entry, self::NAMES, true)) {
                    continue;
                }
                $text = file_get_contents($path);
                if ($text === false) {
                    throw new RuntimeException('Cannot read project file: ' . $name);
                }
                foreach (self::Inspect($text) as $error) {
                    $errors[] = $name . ':' . $error['line'] . ': ' . $error['reason'];
                }
            }
        };
        $walk('');
        return $errors;
    }

    public static function Inspect(string $text): array
    {
        $errors = [];
        // Explicit code points keep the checker itself free of damaged text. Both
        // Windows-1252 and Latin-1 interpretations of UTF-8 umlauts are covered.
        $pattern = '/\x{00C3}[\x{0192}\x{0083}\x{00A4}\x{00B6}\x{00BC}\x{009F}\x{0178}\x{0084}\x{201E}\x{0096}\x{2013}\x{009C}\x{0153}]/u';
        foreach (explode("\n", $text) as $index => $line) {
            $reason = preg_match('//u', $line) !== 1 ? 'Invalid UTF-8' : '';
            if ($reason === '' && str_contains($line, "\u{FFFD}")) {
                $reason = 'Unicode replacement character';
            }
            if ($reason === '' && preg_match($pattern, $line) === 1) {
                $reason = 'Possible corrupted German character encoding';
            }
            if ($reason !== '') {
                $errors[] = ['line' => $index + 1, 'reason' => $reason];
            }
        }
        return $errors;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $root = $argv[1] ?? getcwd();
    if (!is_string($root) || !is_dir($root)) {
        fwrite(STDERR, "The encoding validation root does not exist.\n");
        exit(1);
    }
    $errors = ProjectEncodingCheck::Scan($root);
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    if ($errors !== []) {
        exit(1);
    }
    echo "Project text encoding passed.\n";
}
