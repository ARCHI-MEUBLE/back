<?php

declare(strict_types=1);

namespace App\Lib\Validation;

final class Coercion
{
    public const INVALID = "\0invalid";

    public static function to(string $type, mixed $value): mixed
    {
        return match ($type) {
            'string' => is_scalar($value) ? trim((string) $value) : self::INVALID,
            'int' => self::int($value),
            'float' => is_numeric($value) ? (float) $value : self::INVALID,
            'bool' => self::bool($value),
            'array' => is_array($value) ? $value : self::INVALID,
            default => $value,
        };
    }

    private static function int(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }
        return self::INVALID;
    }

    private static function bool(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }
        if (is_string($value) && in_array(strtolower($value), ['0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'], true)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        return self::INVALID;
    }
}
