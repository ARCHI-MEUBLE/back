<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Db\Connection;
use App\Lib\Validation\ValidationException;

final class FacadeDrillingNormalizer
{
    private const HINGE_HOLES = 'Trous pour charnières';
    private const MAX_DRILLINGS = 30;

    public function __construct(private readonly Connection $db) {}

    public function normalize(mixed $drillings, array $settings): array
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

    public function price(mixed $drillings): float
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
