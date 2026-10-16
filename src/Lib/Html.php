<?php

declare(strict_types=1);

namespace App\Lib;

final class Html
{
    public static function e(mixed $value): string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function escapeAll(array $values): array
    {
        $escaped = [];
        foreach ($values as $key => $value) {
            $escaped[$key] = is_array($value) ? self::escapeAll($value) : (is_string($value) ? self::e($value) : $value);
        }
        return $escaped;
    }
}
