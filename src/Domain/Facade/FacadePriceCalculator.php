<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Db\Connection;
use App\Lib\Validation\ValidationException;

final class FacadePriceCalculator
{
    public const SETTINGS = [
        'material_price_per_m2' => 150.0,
        'hinge_base_price' => 34.20,
        'hinge_coefficient' => 0.05,
        'max_width' => 600.0,
        'max_height' => 2300.0,
        'fixed_depth' => 19.0,
        'hinge_edge_margin' => 150.0,
        'hinge_hole_diameter' => 26.0,
    ];
    public const POSITIVE_SETTINGS = ['material_price_per_m2', 'max_width', 'max_height', 'fixed_depth', 'hinge_edge_margin', 'hinge_hole_diameter'];
    public const HINGE_TYPES = ['no-hole-no-hinge', 'hole-with-applied-hinge', 'hole-with-twin-hinge', 'hole-with-integrated-hinge'];

    private const NO_HINGE = 'no-hole-no-hinge';
    private const MAX_HINGES = 5;

    private readonly FacadeConfigurationNormalizer $normalizer;
    private readonly FacadeDrillingNormalizer $drillings;

    public function __construct(private readonly Connection $db)
    {
        $this->drillings = new FacadeDrillingNormalizer($db);
        $this->normalizer = new FacadeConfigurationNormalizer($db, $this->drillings);
    }

    public function unitPrice(array $config): float
    {
        $settings = $this->settings();
        [$width, $height] = FacadeConfigurationNormalizer::dimensions($config, $settings);
        $surfacePrice = ($width / 1000) * ($height / 1000) * $this->materialPricePerM2($config, $settings);
        $hinges = is_array($config['hinges'] ?? null) ? $config['hinges'] : [];
        $hingeType = is_string($hinges['type'] ?? null) ? $hinges['type'] : self::NO_HINGE;
        $hingeCount = self::hingeCount($hinges['count'] ?? null);
        $withHinges = $hingeType !== self::NO_HINGE && $hingeCount > 0;
        $base = $surfacePrice + ($withHinges ? $settings['hinge_base_price'] * $hingeCount : 0.0);
        $supplement = $withHinges ? $base * ($settings['hinge_coefficient'] * $hingeCount) : 0.0;
        return round($base + $supplement + $this->drillings->price($config['drillings'] ?? []), 2);
    }

    public function normalize(array $config): array
    {
        return $this->normalizer->normalize($config, $this->settings());
    }

    public function settings(): array
    {
        $settings = self::SETTINGS;
        foreach ($this->db->query('SELECT setting_key, setting_value FROM facade_settings') as $row) {
            $key = (string) $row['setting_key'];
            if (!array_key_exists($key, self::SETTINGS) || !is_numeric($row['setting_value'] ?? null)) {
                continue;
            }
            $value = (float) $row['setting_value'];
            if ($value < 0 || ($value <= 0 && in_array($key, self::POSITIVE_SETTINGS, true))) {
                continue;
            }
            $settings[$key] = $value;
        }
        return $settings;
    }

    private static function hingeCount(mixed $count): int
    {
        return is_numeric($count) ? max(0, min(self::MAX_HINGES, (int) $count)) : 0;
    }

    private function materialPricePerM2(array $config, array $settings): float
    {
        $materialId = is_array($config['material'] ?? null) && is_numeric($config['material']['id'] ?? null) ? (int) $config['material']['id'] : 0;
        $price = $this->db->scalar('SELECT price_per_m2 FROM facade_materials WHERE id = ? AND is_active IS NOT FALSE', [$materialId]);
        if ($price === null) {
            if ($this->hasActiveMaterial()) {
                throw new ValidationException('Matériau de façade invalide');
            }
            return $settings['material_price_per_m2'];
        }
        return (float) $price > 0 ? (float) $price : $settings['material_price_per_m2'];
    }

    private function hasActiveMaterial(): bool
    {
        return $this->db->scalar('SELECT 1 FROM facade_materials WHERE is_active IS NOT FALSE LIMIT 1') !== null;
    }
}
