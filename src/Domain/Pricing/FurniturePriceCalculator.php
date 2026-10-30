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
        'delivery' => 'Livraison et manutention',
    ];

    private const ROUNDING = 10;

    public function __construct(private readonly FurniturePricingGrid $grid = new FurniturePricingGrid()) {}

    public static function fromConfigRows(array $rows): self
    {
        return new self(FurniturePricingGrid::fromConfigRows($rows));
    }

    public function estimate(array $config): array
    {
        $dimensions = is_array($config['dimensions'] ?? null) ? $config['dimensions'] : [];
        $width = self::millimetres($dimensions['width'] ?? null, 'largeur');
        $height = self::millimetres($dimensions['height'] ?? null, 'hauteur');
        $depth = self::millimetres($dimensions['depth'] ?? null, 'profondeur');
        $styling = is_array($config['styling'] ?? null) ? $config['styling'] : [];
        $materialPrice = $this->grid->get('materials', self::materialKey($styling['materialKey'] ?? $styling['finish'] ?? null), 'price_per_m2');
        $lines = new FurnitureCostLines($this->grid, $materialPrice, $depth);

        $lines->add('workshop', $this->grid->get('workshop', 'fixed', 'price'));
        [$w, $h, $d] = [$width / 1000, $height / 1000, $depth / 1000];
        $carcass = 2 * $h * $d + 2 * $w * $d + $w * $h * $this->grid->get('panels', 'back', 'surface_factor');
        $lines->add('panels', $carcass * $materialPrice);
        $zone = $config['advancedZones'] ?? $config['rootZone'] ?? null;
        if (is_array($zone)) {
            $lines->zone($zone, $width, $height);
        }
        $features = is_array($config['features'] ?? null) ? $config['features'] : [];
        $globalDoor = $features['doorType'] ?? 'none';
        if ($globalDoor === 'single' || $globalDoor === 'double') {
            $lines->door($globalDoor === 'double' ? 'door_double' : 'door', null, $width, $height);
        }
        $lines->base((string) ($styling['socle'] ?? $config['socle'] ?? 'none'), $width);
        $lines->add('delivery', $w * $h * $this->grid->get('delivery', 'handling', 'price_per_m2'));

        return $this->summary($lines);
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
            'price' => self::round($total),
            'low' => self::round($total - $sigma),
            'high' => self::round($total + $sigma),
            'sigma' => self::round($sigma),
            'lines' => array_map(
                static fn(string $key): array => ['key' => $key, 'label' => self::LINE_LABELS[$key], 'amount' => (int) round($lines->amount($key))],
                array_keys(self::LINE_LABELS),
            ),
        ];
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
