<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final class FurniturePricingGrid
{
    public const DEFAULTS = [
        'workshop' => ['fixed' => ['price' => 600]],
        'materials' => ['default' => ['price_per_m2' => 95]],
        'panels' => ['back' => ['surface_factor' => 0.5]],
        'fronts' => [
            'door' => ['fixed_price' => 65],
            'drawer' => ['fixed_price' => 110],
            'push' => ['supplement' => 30],
            'facade' => ['material_factor' => 1.3],
            'glass' => ['supplement_per_m2' => 0],
        ],
        'handles' => [
            'vertical_bar' => ['price_per_unit' => 25],
            'horizontal_bar' => ['price_per_unit' => 25],
            'knob' => ['price_per_unit' => 15],
            'recessed' => ['price_per_unit' => 30],
        ],
        'options' => [
            'dressing_rod' => ['price_per_linear_meter' => 45],
            'led' => ['price_per_linear_meter' => 60],
            'cable_hole' => ['fixed_price' => 20],
            'flush_mounting' => ['supplement' => 0],
        ],
        'bases' => [
            'metal' => ['price_per_foot' => 20, 'foot_spacing_mm' => 1000, 'min_pairs' => 2],
            'wood' => ['price_per_linear_meter' => 110],
        ],
        'shipping' => [
            'furniture' => ['price_per_m2' => 45, 'minimum' => 0],
            'parcel' => ['flat' => 0, 'free_from' => 0],
        ],
        'rounding' => ['price' => ['step' => 10]],
        'payment' => ['deposit' => ['default_percent' => 30]],
        'uncertainty' => [
            'workshop' => ['relative' => 0.15],
            'panels' => ['relative' => 0.15],
            'fronts' => ['relative' => 0.25],
            'hardware' => ['relative' => 0.20],
        ],
    ];

    private array $params;

    public function __construct(array $params = [])
    {
        $this->params = array_replace_recursive(self::DEFAULTS, $params);
    }

    public static function fromConfigRows(array $rows): self
    {
        $params = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_numeric($row['param_value'] ?? null)) {
                $params[(string) $row['category']][(string) $row['item_type']][(string) $row['param_name']] = (float) $row['param_value'];
            }
        }
        return new self($params);
    }

    public function get(string $category, string $item, string $name): float
    {
        return (float) ($this->params[$category][$item][$name] ?? self::DEFAULTS[$category][$item][$name] ?? 0);
    }

    public function has(string $category, string $item): bool
    {
        return isset($this->params[$category][$item]);
    }
}
