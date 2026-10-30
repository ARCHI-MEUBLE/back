<?php

declare(strict_types=1);

namespace Tests\Support;

use stdClass;

final class Shape
{
    public static function of(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $shape = [];
            foreach (get_object_vars($value) as $key => $item) {
                $shape[$key] = self::of($item);
            }
            ksort($shape);
            return ['object' => $shape];
        }
        if (is_array($value)) {
            if ($value === []) {
                return ['list' => 'empty'];
            }
            $merged = null;
            foreach ($value as $item) {
                $merged = $merged === null ? self::of($item) : self::merge($merged, self::of($item));
            }
            return ['list' => $merged];
        }
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'bool',
            is_int($value), is_float($value) => 'number',
            default => 'string',
        };
    }

    public static function merge(mixed $a, mixed $b): mixed
    {
        if ($a === $b) {
            return $a;
        }
        if (is_array($a) && is_array($b) && isset($a['object'], $b['object']) && is_array($a['object']) && is_array($b['object'])) {
            $keys = array_unique(array_merge(array_keys($a['object']), array_keys($b['object'])));
            $shape = [];
            foreach ($keys as $key) {
                $shape[$key] = self::merge($a['object'][$key] ?? 'absent', $b['object'][$key] ?? 'absent');
            }
            ksort($shape);
            return ['object' => $shape];
        }
        if (is_array($a) && is_array($b) && isset($a['list'], $b['list'])) {
            if ($a['list'] === 'empty') {
                return $b;
            }
            if ($b['list'] === 'empty') {
                return $a;
            }
            return ['list' => self::merge($a['list'], $b['list'])];
        }
        $alternatives = array_merge(self::alternatives($a), self::alternatives($b));
        $unique = array_values(array_unique(array_map(
            static fn(mixed $alternative): string => json_encode($alternative, JSON_THROW_ON_ERROR),
            $alternatives,
        )));
        sort($unique);
        return ['union' => array_map(static fn(string $encoded): mixed => json_decode($encoded, true), $unique)];
    }

    private static function alternatives(mixed $shape): array
    {
        if (is_array($shape) && isset($shape['union']) && is_array($shape['union'])) {
            return $shape['union'];
        }
        return [$shape];
    }
}
