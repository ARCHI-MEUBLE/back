<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Db\Connection;

final class MaterialPriceRepository
{
    public function __construct(private readonly Connection $db) {}

    public function forColors(array $colorIds): array
    {
        $sql = 'SELECT 0 AS part, id AS ord, NULL::integer AS color_id, material, price_per_m2 FROM material_prices';
        $ids = array_values(array_filter($colorIds, 'is_int'));
        if ($ids !== []) {
            $sql .= ' UNION ALL SELECT 1, sc.id, sc.id, st.material, COALESCE(sc.furniture_price_per_m2, mp.price_per_m2) FROM sample_colors sc
                JOIN sample_types st ON st.id = sc.type_id
                LEFT JOIN material_prices mp ON LOWER(mp.material) = LOWER(TRIM(st.material))
                WHERE sc.id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')';
        }
        return $this->db->query($sql . ' ORDER BY part, ord', $ids);
    }

    public function all(): array
    {
        $rows = $this->db->query(
            'WITH used AS (
                SELECT LOWER(TRIM(material)) AS name_key, MIN(TRIM(material)) AS material, COUNT(*) AS colors
                FROM sample_types WHERE TRIM(material) <> \'\' GROUP BY LOWER(TRIM(material))
            )
            SELECT COALESCE(mp.material, used.material) AS material, mp.price_per_m2, COALESCE(used.colors, 0) AS colors
            FROM used FULL OUTER JOIN material_prices mp ON LOWER(mp.material) = used.name_key
            ORDER BY LOWER(COALESCE(mp.material, used.material))',
        );
        return array_map(static fn(array $row): array => [
            'material' => (string) $row['material'],
            'price_per_m2' => $row['price_per_m2'] === null ? null : (string) $row['price_per_m2'],
            'colors' => (int) $row['colors'],
        ], $rows);
    }

    public function set(string $material, float $pricePerM2): void
    {
        $this->db->execute(
            'INSERT INTO material_prices (material, price_per_m2) VALUES (?, ?)
             ON CONFLICT ((LOWER(material))) DO UPDATE SET price_per_m2 = EXCLUDED.price_per_m2, updated_at = CURRENT_TIMESTAMP',
            [$material, $pricePerM2],
        );
    }

    public function delete(string $material): bool
    {
        return $this->db->execute('DELETE FROM material_prices WHERE LOWER(material) = LOWER(?)', [$material]) > 0;
    }

    public function colorCount(string $material): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM sample_types WHERE LOWER(TRIM(material)) = LOWER(?)', [$material]);
    }
}
