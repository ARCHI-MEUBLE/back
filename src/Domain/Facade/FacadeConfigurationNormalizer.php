<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Db\Connection;
use App\Lib\Validation\ValidationException;

final class FacadeConfigurationNormalizer
{
    private const NO_HINGE = 'no-hole-no-hinge';
    private const MAX_HINGES = 5;
    private const MIN_SIZE = 50.0;

    public function __construct(
        private readonly Connection $db,
        private readonly FacadeDrillingNormalizer $drillings,
    ) {}

    public function normalize(array $config, array $settings): array
    {
        [$width, $height] = self::dimensions($config, $settings);
        $hinges = self::hinges($config['hinges'] ?? null);
        return [
            'width' => self::number($width),
            'height' => self::number($height),
            'depth' => self::number($settings['fixed_depth']),
            'material' => $this->material($config['material'] ?? null, $settings),
            'hinges' => $hinges,
            'drillings' => array_merge(
                self::hingeHoles($hinges, $height, $settings),
                $this->drillings->normalize($config['drillings'] ?? [], $settings),
            ),
        ];
    }

    public static function dimensions(array $config, array $settings): array
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
        if (!is_string($type) || !in_array($type, FacadePriceCalculator::HINGE_TYPES, true)) {
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
                'typeName' => 'Trous pour charnières',
                'x' => $hinges['direction'] === 'left' ? 5 : 95,
                'y' => round(max(0.0, min(100.0, $margin + $spacing * $index)), 4),
                'diameter' => self::number($settings['hinge_hole_diameter']),
                'price' => 0,
            ];
        }
        return $holes;
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

    private function hasActiveMaterial(): bool
    {
        return $this->db->scalar('SELECT 1 FROM facade_materials WHERE is_active IS NOT FALSE LIMIT 1') !== null;
    }

    private static function number(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : $value;
    }
}
