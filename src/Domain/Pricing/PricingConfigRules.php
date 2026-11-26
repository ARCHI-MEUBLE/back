<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Lib\Validation\ValidationException;

final class PricingConfigRules
{
    public const UNITS = ['eur', 'eur_m2', 'eur_linear_m', 'coefficient', 'ratio', 'mm', 'percent', 'count'];

    public static function value(array $row, mixed $value): float
    {
        if (is_bool($value) || !is_numeric($value)) {
            throw new ValidationException('La valeur doit être un nombre');
        }
        $number = (float) $value;
        $unit = is_string($row['unit'] ?? null) ? $row['unit'] : '';
        [$min, $max, $message] = self::bounds($unit);
        if (is_nan($number) || $number < $min || $number > $max || ($unit === 'count' && floor($number) !== $number)) {
            throw new ValidationException($message);
        }
        $key = implode('.', [$row['category'] ?? '', $row['item_type'] ?? '', $row['param_name'] ?? '']);
        if ($key === 'bases.metal.foot_spacing_mm' && $number < 100) {
            throw new ValidationException('L\'écart entre deux paires de pieds doit être d\'au moins 100 mm');
        }
        if ($key === 'rounding.price.step' && $number < 1) {
            throw new ValidationException('L\'arrondi du prix doit être d\'au moins 1 €');
        }
        return $number;
    }

    private static function bounds(string $unit): array
    {
        return match (true) {
            str_starts_with($unit, 'eur'), str_contains($unit, '€') => [0, 100000, 'Le montant doit être compris entre 0 et 100 000 €'],
            $unit === 'ratio' => [0, 1, 'Le taux doit être compris entre 0 et 1 (0,15 = 15 %)'],
            $unit === 'coefficient' => [0, 10, 'Le coefficient doit être compris entre 0 et 10'],
            $unit === 'mm' => [1, 5000, 'La longueur doit être comprise entre 1 et 5 000 mm'],
            $unit === 'percent' => [0, 100, 'Le pourcentage doit être compris entre 0 et 100'],
            $unit === 'count' => [0, 20, 'Le nombre doit être un entier compris entre 0 et 20'],
            default => [0, 100000, 'La valeur doit être comprise entre 0 et 100 000'],
        };
    }
}
