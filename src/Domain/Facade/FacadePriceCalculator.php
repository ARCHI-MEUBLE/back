<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Db\Connection;
use App\Lib\Validation\ValidationException;

final class FacadePriceCalculator
{
    private const NO_HINGE = 'no-hole-no-hinge';
    private const MAX_DRILLINGS = 30;

    public function __construct(private readonly Connection $db) {}

    public function unitPrice(array $config): float
    {
        $settings = $this->settings();
        $width = is_numeric($config['width'] ?? null) ? (float) $config['width'] : 0.0;
        $height = is_numeric($config['height'] ?? null) ? (float) $config['height'] : 0.0;
        if ($width < 50 || $height < 50 || $width > ($settings['max_width'] ?? 3000) || $height > ($settings['max_height'] ?? 3000)) {
            throw new ValidationException('Dimensions de façade invalides');
        }
        $surfacePrice = ($width / 1000) * ($height / 1000) * $this->materialPricePerM2($config, $settings);
        $hinges = is_array($config['hinges'] ?? null) ? $config['hinges'] : [];
        $hingeType = is_string($hinges['type'] ?? null) ? $hinges['type'] : self::NO_HINGE;
        $hingeCount = is_numeric($hinges['count'] ?? null) ? max(0, min(5, (int) $hinges['count'])) : 0;
        $withHinges = $hingeType !== self::NO_HINGE && $hingeCount > 0;
        $base = $surfacePrice + ($withHinges ? ($settings['hinge_base_price'] ?? 34.20) * $hingeCount : 0.0);
        $supplement = $withHinges ? $base * (($settings['hinge_coefficient'] ?? 0.05) * $hingeCount) : 0.0;
        return round($base + $supplement + $this->drillingsPrice($config['drillings'] ?? []), 2);
    }

    private function materialPricePerM2(array $config, array $settings): float
    {
        $materialId = is_array($config['material'] ?? null) && is_numeric($config['material']['id'] ?? null) ? (int) $config['material']['id'] : 0;
        $price = $this->db->scalar('SELECT price_per_m2 FROM facade_materials WHERE id = ? AND is_active IS NOT FALSE', [$materialId]);
        if ($price === null) {
            if ($this->db->scalar('SELECT 1 FROM facade_materials WHERE is_active IS NOT FALSE LIMIT 1') !== null) {
                throw new ValidationException('Matériau de façade invalide');
            }
            return $settings['material_price_per_m2'] ?? 150.0;
        }
        return (float) $price > 0 ? (float) $price : ($settings['material_price_per_m2'] ?? 150.0);
    }

    private function drillingsPrice(mixed $drillings): float
    {
        if (!is_array($drillings)) {
            return 0.0;
        }
        if (count($drillings) > self::MAX_DRILLINGS) {
            throw new ValidationException('Trop de perçages sur cette façade');
        }
        $total = 0.0;
        foreach ($drillings as $drilling) {
            $typeId = is_array($drilling) && is_numeric($drilling['drilling_type_id'] ?? null) ? (int) $drilling['drilling_type_id'] : 0;
            if ($typeId > 0) {
                $price = $this->db->scalar('SELECT price FROM facade_drilling_types WHERE id = ? AND is_active IS NOT FALSE', [$typeId]);
                if ($price === null) {
                    throw new ValidationException('Type de perçage invalide');
                }
                $total += (float) $price;
            }
        }
        return $total;
    }

    private function settings(): array
    {
        $settings = [];
        foreach ($this->db->query('SELECT setting_key, setting_value FROM facade_settings') as $row) {
            if (is_numeric($row['setting_value'] ?? null) && (float) $row['setting_value'] > 0) {
                $settings[(string) $row['setting_key']] = (float) $row['setting_value'];
            }
        }
        return $settings;
    }
}
