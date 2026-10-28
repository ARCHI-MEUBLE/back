<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Shared\DomainException;

/**
 * Indicative price of a configured piece of furniture, computed from the configuration the configurator saves
 * (dimensions, material, base, zone tree). Every cost line carries a relative uncertainty; the lines being independent,
 * the standard deviation of the total is the root of the sum of their squares, and the range shown is price ± sigma.
 */
final class FurniturePriceCalculator
{
    public const DEFAULTS = [
        'workshop' => ['fixed' => ['price' => 600]],
        'materials' => [
            'agglomere' => ['price_per_m2' => 75],
            'mdf_melamine' => ['price_per_m2' => 95],
            'plaque_bois' => ['price_per_m2' => 125],
        ],
        'panels' => ['back' => ['surface_factor' => 0.5]],
        'fronts' => [
            'door' => ['fixed_price' => 65],
            'drawer' => ['fixed_price' => 110],
            'push' => ['supplement' => 30],
            'facade' => ['material_factor' => 1.3],
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
        ],
        'bases' => [
            'metal' => ['price_per_foot' => 20, 'foot_spacing_mm' => 1000],
            'wood' => ['price_per_linear_meter' => 110],
        ],
        'delivery' => ['handling' => ['price_per_m2' => 45]],
        'uncertainty' => [
            'workshop' => ['relative' => 0.15],
            'panels' => ['relative' => 0.15],
            'fronts' => ['relative' => 0.25],
            'hardware' => ['relative' => 0.20],
            'delivery' => ['relative' => 0.40],
        ],
    ];

    public const LINE_LABELS = [
        'workshop' => 'Conception et fabrication',
        'panels' => 'Panneaux (caisson, fond, étagères, montants)',
        'fronts' => 'Portes et tiroirs',
        'hardware' => 'Poignées, socle et options',
        'delivery' => 'Livraison et manutention',
    ];

    private const DOORS = ['door', 'door_right', 'door_double', 'mirror_door', 'mirror_door_right', 'push_door', 'push_door_right'];
    private const DRAWERS = ['drawer', 'push_drawer'];
    private const MAX_DEPTH = 12;
    private const MAX_ZONES = 400;
    private const ROUNDING = 10;

    private array $params;
    private array $lines = [];
    private int $zoneCount = 0;

    public function __construct(array $params = [])
    {
        $this->params = array_replace_recursive(self::DEFAULTS, $params);
    }

    /** Rows of pricing_config grouped as [category][item_type][param_name] => value, over the defaults. */
    public static function fromConfigRows(array $rows): self
    {
        $params = [];
        foreach ($rows as $row) {
            if (is_numeric($row['param_value'] ?? null)) {
                $params[$row['category']][$row['item_type']][$row['param_name']] = (float) $row['param_value'];
            }
        }
        return new self($params);
    }

    /**
     * @return array{price: int, low: int, high: int, sigma: int, lines: list<array{key: string, label: string, amount: int}>}
     */
    public function estimate(array $config): array
    {
        $dimensions = is_array($config['dimensions'] ?? null) ? $config['dimensions'] : [];
        $width = self::millimetres($dimensions['width'] ?? null, 'largeur');
        $height = self::millimetres($dimensions['height'] ?? null, 'hauteur');
        $depth = self::millimetres($dimensions['depth'] ?? null, 'profondeur');
        $styling = is_array($config['styling'] ?? null) ? $config['styling'] : [];
        $materialPrice = $this->param('materials', self::materialKey($styling['materialKey'] ?? $styling['finish'] ?? null), 'price_per_m2');
        $zone = $config['advancedZones'] ?? $config['rootZone'] ?? null;

        $this->lines = array_fill_keys(array_keys(self::LINE_LABELS), 0.0);
        $this->zoneCount = 0;
        $this->lines['workshop'] = $this->param('workshop', 'fixed', 'price');

        [$w, $h, $d] = [$width / 1000, $height / 1000, $depth / 1000];
        $carcass = 2 * $h * $d + 2 * $w * $d + $w * $h * $this->param('panels', 'back', 'surface_factor');
        $this->lines['panels'] = $carcass * $materialPrice;
        if (is_array($zone)) {
            $this->zone($zone, $width, $height, $depth, $materialPrice, 0);
        }

        $features = is_array($config['features'] ?? null) ? $config['features'] : [];
        $globalDoor = $features['doorType'] ?? 'none';
        if ($globalDoor === 'single' || $globalDoor === 'double') {
            $this->door($globalDoor === 'double' ? 'door_double' : 'door', null, $width, $height, $materialPrice);
        }

        $this->base((string) ($styling['socle'] ?? $config['socle'] ?? 'none'), $width);
        $this->lines['delivery'] = $w * $h * $this->param('delivery', 'handling', 'price_per_m2');

        $total = array_sum($this->lines);
        $variance = 0.0;
        foreach ($this->lines as $key => $amount) {
            $variance += ($amount * $this->param('uncertainty', $key, 'relative')) ** 2;
        }
        $sigma = sqrt($variance);

        return [
            'price' => self::round($total),
            'low' => self::round($total - $sigma),
            'high' => self::round($total + $sigma),
            'sigma' => self::round($sigma),
            'lines' => array_map(
                fn(string $key): array => ['key' => $key, 'label' => self::LINE_LABELS[$key], 'amount' => (int) round($this->lines[$key])],
                array_keys(self::LINE_LABELS),
            ),
        ];
    }

    private function zone(array $zone, float $width, float $height, float $depth, float $materialPrice, int $level): void
    {
        if ($level > self::MAX_DEPTH || ++$this->zoneCount > self::MAX_ZONES) {
            throw new DomainException('Configuration trop complexe');
        }
        $content = is_string($zone['content'] ?? null) ? $zone['content'] : null;
        $isLeaf = ($zone['type'] ?? 'leaf') === 'leaf';
        $doorContent = is_string($zone['doorContent'] ?? null) ? $zone['doorContent'] : ($isLeaf ? $content : null);
        $handle = is_string($zone['handleType'] ?? null) ? $zone['handleType'] : null;

        if ($doorContent !== null && in_array($doorContent, self::DOORS, true)) {
            $this->door($doorContent, $handle, $width, $height, $materialPrice);
        }

        $children = is_array($zone['children'] ?? null) ? array_values(array_filter($zone['children'], 'is_array')) : [];
        if ($isLeaf || $children === []) {
            $this->leaf($zone, $content, $handle, $width, $height, $depth, $materialPrice);
            return;
        }

        $horizontal = $zone['type'] === 'horizontal';
        $count = count($children);
        $drawerStack = $horizontal && $count >= 2 && array_reduce($children, fn(bool $all, array $c): bool => $all && in_array($c['content'] ?? null, self::DRAWERS, true), true);
        if (!$drawerStack) {
            $separator = $horizontal ? $width * $depth : $height * $depth;
            $this->lines['panels'] += ($count - 1) * $separator / 1_000_000 * $materialPrice;
        }
        foreach ($children as $index => $child) {
            $ratio = self::ratio($zone, $index, $count);
            $this->zone($child, $horizontal ? $width : $width * $ratio, $horizontal ? $height * $ratio : $height, $depth, $materialPrice, $level + 1);
        }
    }

    private function leaf(array $zone, ?string $content, ?string $handle, float $width, float $height, float $depth, float $materialPrice): void
    {
        if ($content !== null && in_array($content, self::DRAWERS, true)) {
            $this->lines['fronts'] += $this->param('fronts', 'drawer', 'fixed_price') + $this->facade($width, $height, $materialPrice);
            if ($content === 'push_drawer') {
                $this->lines['hardware'] += $this->param('fronts', 'push', 'supplement');
            } else {
                $this->lines['hardware'] += $this->handle($handle);
            }
        }
        if ($content === 'shelf') {
            $this->lines['panels'] += $width * $depth / 1_000_000 * $materialPrice;
        }
        if ($content === 'dressing' || ($zone['hasDressing'] ?? false) === true) {
            $this->lines['hardware'] += $width / 1000 * $this->param('options', 'dressing_rod', 'price_per_linear_meter');
        }
        if (($zone['hasLight'] ?? false) === true) {
            $this->lines['hardware'] += $width / 1000 * $this->param('options', 'led', 'price_per_linear_meter');
        }
        if (($zone['hasCableHole'] ?? false) === true) {
            $this->lines['hardware'] += $this->param('options', 'cable_hole', 'fixed_price');
        }
    }

    private function door(string $type, ?string $handle, float $width, float $height, float $materialPrice): void
    {
        $leaves = $type === 'door_double' ? 2 : 1;
        $this->lines['fronts'] += $leaves * $this->param('fronts', 'door', 'fixed_price') + $this->facade($width, $height, $materialPrice);
        $this->lines['hardware'] += str_starts_with($type, 'push_')
            ? $leaves * $this->param('fronts', 'push', 'supplement')
            : $leaves * $this->handle($handle);
    }

    private function facade(float $width, float $height, float $materialPrice): float
    {
        return $width * $height / 1_000_000 * $materialPrice * $this->param('fronts', 'facade', 'material_factor');
    }

    private function handle(?string $type): float
    {
        return $type !== null && isset($this->params['handles'][$type]) ? $this->param('handles', $type, 'price_per_unit') : 0.0;
    }

    private function base(string $socle, float $width): void
    {
        if ($socle === 'metal') {
            $pairs = max(2, (int) ceil($width / $this->param('bases', 'metal', 'foot_spacing_mm')) + 1);
            $this->lines['hardware'] += 2 * $pairs * $this->param('bases', 'metal', 'price_per_foot');
        } elseif ($socle === 'wood') {
            $this->lines['hardware'] += $width / 1000 * $this->param('bases', 'wood', 'price_per_linear_meter');
        }
    }

    private function param(string $category, string $item, string $name): float
    {
        return (float) ($this->params[$category][$item][$name] ?? self::DEFAULTS[$category][$item][$name] ?? 0);
    }

    private static function ratio(array $zone, int $index, int $count): float
    {
        $ratios = $zone['splitRatios'] ?? null;
        if (is_array($ratios) && count($ratios) === $count && is_numeric($ratios[$index] ?? null)) {
            return max(0.0, (float) $ratios[$index] / 100);
        }
        if ($count === 2 && is_numeric($zone['splitRatio'] ?? null)) {
            $first = (float) $zone['splitRatio'] / 100;
            return max(0.0, $index === 0 ? $first : 1 - $first);
        }
        return 1 / $count;
    }

    private static function materialKey(mixed $value): string
    {
        $text = is_string($value) ? mb_strtolower($value) : '';
        return match (true) {
            str_contains($text, 'aggl') => 'agglomere',
            str_contains($text, 'bois') => 'plaque_bois',
            default => 'mdf_melamine',
        };
    }

    private static function millimetres(mixed $value, string $label): float
    {
        if (!is_numeric($value) || (float) $value < 100 || (float) $value > 5000) {
            throw new DomainException("Dimension invalide : $label");
        }
        return (float) $value;
    }

    private static function round(float $amount): int
    {
        return (int) (round(max(0.0, $amount) / self::ROUNDING) * self::ROUNDING);
    }
}
