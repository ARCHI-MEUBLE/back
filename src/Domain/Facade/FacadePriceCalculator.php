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
    private const HINGE_HOLES = 'Trous pour charnières';
    private const MAX_HINGES = 5;
    private const MAX_DRILLINGS = 30;
    private const MIN_SIZE = 50.0;

    public function __construct(private readonly Connection $db) {}

    public function unitPrice(array $config): float
    {
        $settings = $this->settings();
        [$width, $height] = self::dimensions($config, $settings);
        $surfacePrice = ($width / 1000) * ($height / 1000) * $this->materialPricePerM2($config, $settings);
        $hinges = is_array($config['hinges'] ?? null) ? $config['hinges'] : [];
        $hingeType = is_string($hinges['type'] ?? null) ? $hinges['type'] : self::NO_HINGE;
        $hingeCount = self::hingeCount($hinges['count'] ?? null);
        $withHinges = $hingeType !== self::NO_HINGE && $hingeCount > 0;
        $base = $surfacePrice + ($withHinges ? $settings['hinge_base_price'] * $hingeCount : 0.0);
        $supplement = $withHinges ? $base * ($settings['hinge_coefficient'] * $hingeCount) : 0.0;
        return round($base + $supplement + $this->drillingsPrice($config['drillings'] ?? []), 2);
    }

    public function normalize(array $config): array
    {
        $settings = $this->settings();
        [$width, $height] = self::dimensions($config, $settings);
        $hinges = self::hinges($config['hinges'] ?? null);
        return [
            'width' => self::number($width),
            'height' => self::number($height),
            'depth' => self::number($settings['fixed_depth']),
            'material' => $this->material($config['material'] ?? null, $settings),
            'hinges' => $hinges,
            'drillings' => array_merge(self::hingeHoles($hinges, $height, $settings), $this->customDrillings($config['drillings'] ?? [], $settings)),
        ];
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

    private static function dimensions(array $config, array $settings): array
    {
        $width = is_numeric($config['width'] ?? null) ? (float) $config['width'] : 0.0;
        $height = is_numeric($config['height'] ?? null) ? (float) $config['height'] : 0.0;
        if ($width < self::MIN_SIZE || $height < self::MIN_SIZE || $width > $settings['max_width'] || $height > $settings['max_height']) {
            throw new ValidationException(sprintf(
                'Dimensions de façade invalides : largeur de %d à %d mm, hauteur de %d à %d mm',
                self::MIN_SIZE,
                $settings['max_width'],
                self::MIN_SIZE,
                $settings['max_height'],
            ));
        }
        return [$width, $height];
    }

    private static function hingeCount(mixed $count): int
    {
        return is_numeric($count) ? max(0, min(self::MAX_HINGES, (int) $count)) : 0;
    }

    private static function hinges(mixed $hinges): array
    {
        $hinges = is_array($hinges) ? $hinges : [];
        $type = $hinges['type'] ?? self::NO_HINGE;
        if (!is_string($type) || !in_array($type, self::HINGE_TYPES, true)) {
            throw new ValidationException('Type de charnière invalide');
        }
        $count = self::hingeCount($hinges['count'] ?? null);
        if ($count === 0) {
            $type = self::NO_HINGE;
        }
        return [
            'type' => $type,
            'count' => $type === self::NO_HINGE ? 0 : $count,
            'direction' => ($hinges['direction'] ?? null) === 'left' ? 'left' : 'right',
        ];
    }

    private static function hingeHoles(array $hinges, float $height, array $settings): array
    {
        $count = (int) $hinges['count'];
        if ($hinges['type'] === self::NO_HINGE || $count === 0) {
            return [];
        }
        $margin = $settings['hinge_edge_margin'] / $height * 100;
        $spacing = $count > 1 ? (100 - 2 * $margin) / ($count - 1) : 0.0;
        $holes = [];
        for ($index = 0; $index < $count; $index++) {
            $holes[] = [
                'id' => 'hinge-' . $index,
                'type' => 'circular',
                'typeName' => self::HINGE_HOLES,
                'x' => $hinges['direction'] === 'left' ? 5 : 95,
                'y' => round(max(0.0, min(100.0, $margin + $spacing * $index)), 4),
                'diameter' => self::number($settings['hinge_hole_diameter']),
                'price' => 0,
            ];
        }
        return $holes;
    }

    private function customDrillings(mixed $drillings, array $settings): array
    {
        if (!is_array($drillings)) {
            return [];
        }
        if (count($drillings) > self::MAX_DRILLINGS) {
            throw new ValidationException('Trop de perçages sur cette façade');
        }
        $normalized = [];
        foreach ($drillings as $drilling) {
            if (!is_array($drilling)) {
                throw new ValidationException('Perçage invalide');
            }
            $typeId = is_numeric($drilling['drilling_type_id'] ?? null) ? (int) $drilling['drilling_type_id'] : 0;
            if ($typeId <= 0) {
                if (($drilling['typeName'] ?? null) === self::HINGE_HOLES) {
                    continue;
                }
                throw new ValidationException('Perçage non reconnu : seuls les types de perçage proposés peuvent être commandés');
            }
            $type = $this->db->queryOne('SELECT id, name, price FROM facade_drilling_types WHERE id = ? AND is_active IS NOT FALSE', [$typeId]);
            if ($type === null) {
                throw new ValidationException('Type de perçage invalide');
            }
            $shape = ($drilling['type'] ?? null) === 'rectangular' ? 'rectangular' : 'circular';
            $entry = [
                'id' => 'drilling-' . count($normalized),
                'type' => $shape,
                'typeName' => (string) $type['name'],
                'drilling_type_id' => (int) $type['id'],
                'x' => self::percent($drilling['x'] ?? null),
                'y' => self::percent($drilling['y'] ?? null),
                'price' => (float) $type['price'],
            ];
            if ($shape === 'rectangular') {
                $entry['width'] = self::millimetres($drilling['width'] ?? null, 20.0);
                $entry['height'] = self::millimetres($drilling['height'] ?? null, 20.0);
            } else {
                $entry['diameter'] = self::millimetres($drilling['diameter'] ?? null, $settings['hinge_hole_diameter']);
            }
            $normalized[] = $entry;
        }
        return $normalized;
    }

    private function material(mixed $material, array $settings): array
    {
        $id = is_array($material) && is_numeric($material['id'] ?? null) ? (int) $material['id'] : 0;
        $row = $id > 0
            ? $this->db->queryOne('SELECT id, name, color_hex, texture_url, price_per_m2 FROM facade_materials WHERE id = ? AND is_active IS NOT FALSE', [$id])
            : null;
        if ($row !== null) {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'color_hex' => (string) $row['color_hex'],
                'texture_url' => $row['texture_url'],
                'price_per_m2' => (float) $row['price_per_m2'] > 0 ? (float) $row['price_per_m2'] : $settings['material_price_per_m2'],
            ];
        }
        if ($this->hasActiveMaterial()) {
            throw new ValidationException('Matériau de façade invalide');
        }
        return ['id' => null, 'name' => 'Matériau standard', 'color_hex' => '#D8C7A1', 'texture_url' => null, 'price_per_m2' => $settings['material_price_per_m2']];
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

    private static function percent(mixed $value): float
    {
        return is_numeric($value) ? round(max(0.0, min(100.0, (float) $value)), 4) : 50.0;
    }

    private static function millimetres(mixed $value, float $default): int|float
    {
        return is_numeric($value) && (float) $value > 0 && (float) $value <= 500 ? self::number((float) $value) : self::number($default);
    }

    private static function number(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : $value;
    }
}
