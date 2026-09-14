<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$roots = array_slice($argv, 1);
if ($roots === []) {
    $roots = array_values(array_filter(
        ['src', 'tests', 'tools', 'public', 'bin', 'templates'],
        static fn(string $dir): bool => is_dir(__DIR__ . '/../' . $dir),
    ));
}

$violations = Tools\FileRules::violations(array_map(
    static fn(string $root): string => str_starts_with($root, '/') ? $root : __DIR__ . '/../' . $root,
    $roots,
));

if ($violations !== []) {
    fwrite(STDERR, implode(PHP_EOL, $violations) . PHP_EOL);
    fwrite(STDERR, sprintf('%d file rule violation(s)%s', count($violations), PHP_EOL));
    exit(1);
}

fwrite(STDOUT, 'file rules ok' . PHP_EOL);
