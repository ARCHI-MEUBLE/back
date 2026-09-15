<?php

declare(strict_types=1);

namespace Tools;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class FileRules
{
    public const MAX_LINES = 150;

    private const STRICT_TYPES_STATEMENT = ['declare', '(', 'strict_types', '=', '1', ')', ';'];

    public static function violations(array $roots): array
    {
        $violations = [];
        foreach (self::phpFiles($roots) as $path) {
            $source = file_get_contents($path);
            if ($source === false) {
                throw new RuntimeException('Unreadable file: ' . $path);
            }
            foreach (self::check($source) as $problem) {
                $violations[] = $path . ': ' . $problem;
            }
        }
        sort($violations);
        return $violations;
    }

    public static function check(string $source): array
    {
        $problems = [];
        $lines = substr_count($source, "\n") + ($source !== '' && !str_ends_with($source, "\n") ? 1 : 0);
        if ($lines > self::MAX_LINES) {
            $problems[] = sprintf('%d lines (max %d)', $lines, self::MAX_LINES);
        }
        $opening = [];
        $commentReported = false;
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $opening = self::collect($opening, $token);
                continue;
            }
            if (($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) && !$commentReported) {
                $problems[] = sprintf('comment at line %d', $token[2]);
                $commentReported = true;
            }
            if (!in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $opening = self::collect($opening, $token[1]);
            }
        }
        if (array_map(strtolower(...), $opening) !== self::STRICT_TYPES_STATEMENT) {
            $problems[] = 'missing declare(strict_types=1) as first statement';
        }
        return $problems;
    }

    private static function collect(array $opening, string $text): array
    {
        if (count($opening) < count(self::STRICT_TYPES_STATEMENT)) {
            $opening[] = $text;
        }
        return $opening;
    }

    private static function phpFiles(array $roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            if (!is_string($root)) {
                throw new InvalidArgumentException('Roots must be paths');
            }
            if (is_file($root)) {
                $files[] = $root;
                continue;
            }
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && self::isPhp($file)) {
                    $files[] = $file->getPathname();
                }
            }
        }
        return $files;
    }

    private static function isPhp(SplFileInfo $file): bool
    {
        return $file->isFile() && ($file->getExtension() === 'php' || $file->getFilename() === 'console');
    }
}
