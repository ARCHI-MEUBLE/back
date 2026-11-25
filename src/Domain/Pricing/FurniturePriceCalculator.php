<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Shared\DomainException;

final class FurniturePriceCalculator
{
    public const LINE_LABELS = [
        'workshop' => 'Conception et fabrication',
        'panels' => 'Panneaux (caisson, fond, étagères, montants)',
        'fronts' => 'Portes et tiroirs',
        'hardware' => 'Poignées, socle et options',
    ];

    public const DIMENSION_LIMITS = [
        'width' => ['label' => 'la largeur', 'min' => 300, 'max' => 2800],
        'height' => ['label' => 'la hauteur', 'min' => 300, 'max' => 2200],
        'depth' => ['label' => 'la profondeur', 'min' => 200, 'max' => 800],
    ];

    private const GLOBAL_DOORS = ['single' => 'door', 'double' => 'door_double'];

    public function __construct(
        private readonly FurniturePricingGrid $grid = new FurniturePricingGrid(),
        private readonly HandlePrices $handles = new HandlePrices(),
        private readonly MaterialPrices $materials = new MaterialPrices(),
    ) {}

    public static function fromConfigRows(array $rows, array $handleModelRows = [], array $materialRows = []): self
    {
        return new self(FurniturePricingGrid::fromConfigRows($rows), HandlePrices::fromModelRows($handleModelRows), MaterialPrices::fromRows($materialRows));
    }

    public function estimate(array $config): array
    {
        $dimensions = is_array($config['dimensions'] ?? null) ? $config['dimensions'] : [];
        $width = self::millimetres($dimensions, 'width');
        $height = self::millimetres($dimensions, 'height');
        $depth = self::millimetres($dimensions, 'depth');
        $styling = is_array($config['styling'] ?? null) ? $config['styling'] : [];
        $features = is_array($config['features'] ?? null) ? $config['features'] : [];
        $handles = $this->handles->forFurniture($config['handles'] ?? null);
        $materials = $this->materials->forConfiguration($config, $this->grid->get('materials', 'default', 'price_per_m2'));
        $lines = new FurnitureCostLines($this->grid, $materials, $depth, $handles);

        $lines->add('workshop', $this->grid->get('workshop', 'fixed', 'price'));
        $lines->carcass($width, $height);
        $zone = $config['advancedZones'] ?? $config['rootZone'] ?? null;
        if (is_array($zone)) {
            $lines->zone($zone, $width, $height);
        }
        $globalDoor = is_string($features['doorType'] ?? null) ? (self::GLOBAL_DOORS[$features['doorType']] ?? null) : null;
        if ($globalDoor !== null && !(is_array($zone) && FurnitureCostLines::hasLeafDoor($zone))) {
            $lines->door($globalDoor, $handles->price($this->grid, []), $width, $height, $materials->part('doors'));
        }
        if (($features['mountingStyle'] ?? null) === 'encastre') {
            $lines->add('hardware', $this->grid->get('options', 'flush_mounting', 'supplement'));
        }
        $socle = $styling['socle'] ?? $config['socle'] ?? null;
        $lines->base(is_string($socle) ? $socle : 'none', $width);

        $delivery = (new ShippingCalculator($this->grid))->furniture($width, $height);
        return $this->summary($lines) + ['delivery' => (int) round($delivery)];
    }

    private function summary(FurnitureCostLines $lines): array
    {
        $total = 0.0;
        $variance = 0.0;
        foreach (array_keys(self::LINE_LABELS) as $key) {
            $total += $lines->amount($key);
            $variance += ($lines->amount($key) * $this->grid->get('uncertainty', $key, 'relative')) ** 2;
        }
        $sigma = sqrt($variance);

        return [
            'price' => $this->round($total),
            'low' => $this->round($total - $sigma),
            'high' => $this->round($total + $sigma),
            'sigma' => $this->round($sigma),
            'lines' => array_map(
                static fn(string $key): array => ['key' => $key, 'label' => self::LINE_LABELS[$key], 'amount' => (int) round($lines->amount($key))],
                array_keys(self::LINE_LABELS),
            ),
        ];
    }

    private static function millimetres(array $dimensions, string $key): float
    {
        ['label' => $label, 'min' => $min, 'max' => $max] = self::DIMENSION_LIMITS[$key];
        $value = $dimensions[$key] ?? null;
        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            throw new DomainException(sprintf('Dimension invalide : %s doit être comprise entre %d et %d mm', $label, $min, $max));
        }
        return (float) $value;
    }

    private function round(float $amount): int
    {
        $step = max(1.0, $this->grid->get('rounding', 'price', 'step'));
        return (int) (round(max(0.0, $amount) / $step) * $step);
    }
}
