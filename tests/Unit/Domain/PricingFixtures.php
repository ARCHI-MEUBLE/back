<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Pricing\FurniturePriceCalculator;

trait PricingFixtures
{
    private const MATERIAL_ROWS = [
        ['color_id' => null, 'material' => 'Agglomere', 'price_per_m2' => '75.00'],
        ['color_id' => null, 'material' => 'MDF + revetement (melamine)', 'price_per_m2' => '95.00'],
        ['color_id' => null, 'material' => 'Plaque bois', 'price_per_m2' => '125.00'],
        ['color_id' => 1, 'material' => 'Agglomere', 'price_per_m2' => '75.00'],
        ['color_id' => 2, 'material' => 'MDF + revetement (melamine)', 'price_per_m2' => '95.00'],
        ['color_id' => 3, 'material' => 'Plaque bois', 'price_per_m2' => '125.00'],
        ['color_id' => 4, 'material' => 'Chêne massif', 'price_per_m2' => null],
    ];

    private const HANDLE_MODELS = [['id' => 7, 'price_per_unit' => '25.00'], ['id' => 8, 'price_per_unit' => '12.50']];

    private static function calculator(array $gridRows = [], array $handleModels = []): FurniturePriceCalculator
    {
        return FurniturePriceCalculator::fromConfigRows($gridRows, $handleModels, self::MATERIAL_ROWS);
    }

    private static function grid(string $category, string $item, string $name, float $value): array
    {
        return ['category' => $category, 'item_type' => $item, 'param_name' => $name, 'param_value' => (string) $value];
    }

    private static function config(int $width, int $height, int $depth, array $zone, array $styling = [], array $extra = []): array
    {
        return array_replace_recursive([
            'dimensions' => ['width' => $width, 'height' => $height, 'depth' => $depth],
            'styling' => $styling + ['materialKey' => 'mdf_melamine', 'socle' => 'none'],
            'features' => ['doorType' => 'none'],
            'advancedZones' => $zone,
        ], $extra);
    }

    private static function leaf(?string $content = null, array $extra = []): array
    {
        return ['id' => 'z', 'type' => 'leaf', 'content' => $content ?? 'empty'] + $extra;
    }

    private static function split(string $type, array $children, array $extra = []): array
    {
        return ['id' => 'z', 'type' => $type, 'children' => $children] + $extra;
    }

    private static function line(array $estimate, string $key): int
    {
        foreach ($estimate['lines'] as $line) {
            if ($line['key'] === $key) {
                return $line['amount'];
            }
        }
        self::fail('Missing line ' . $key);
    }
}
