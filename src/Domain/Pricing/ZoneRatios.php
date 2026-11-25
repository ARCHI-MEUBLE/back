<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final class ZoneRatios
{
    public static function of(array $zone, int $count): array
    {
        if ($count < 1) {
            return [];
        }
        $ratios = $zone['splitRatios'] ?? null;
        if (is_array($ratios) && count($ratios) === $count) {
            $values = array_map(static fn(mixed $ratio): float => is_numeric($ratio) ? max(0.0, (float) $ratio) : -1.0, array_values($ratios));
            $sum = array_sum($values);
            if (min($values) >= 0 && $sum > 0) {
                return array_map(static fn(float $value): float => $value / $sum, $values);
            }
        }
        if ($count === 2 && is_numeric($zone['splitRatio'] ?? null)) {
            $first = min(1.0, max(0.0, (float) $zone['splitRatio'] / 100));
            return [$first, 1 - $first];
        }
        return array_fill(0, $count, 1 / $count);
    }
}
