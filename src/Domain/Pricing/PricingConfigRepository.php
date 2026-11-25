<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Db\Connection;

final class PricingConfigRepository
{
    private const COLUMNS = 'id, category, item_type, param_name, param_value, unit, description, is_active, updated_at';

    public function __construct(private readonly Connection $db) {}

    public function search(?int $id, ?string $category, ?string $itemType, bool $activeOnly): array
    {
        $sql = 'SELECT * FROM pricing_config WHERE 1=1';
        $params = [];
        if ($activeOnly) {
            $sql .= ' AND is_active = TRUE';
        }
        if ($id !== null) {
            $sql .= ' AND id = ?';
            $params[] = $id;
        } elseif ($category !== null) {
            $sql .= ' AND category = ?';
            $params[] = $category;
            if ($itemType !== null) {
                $sql .= ' AND item_type = ?';
                $params[] = $itemType;
            }
        }
        return $this->db->query($sql . ' ORDER BY category, item_type, param_name', $params);
    }

    public function all(): array
    {
        return $this->db->query('SELECT ' . self::COLUMNS . ' FROM pricing_config ORDER BY category, item_type, param_name');
    }

    public function find(int $id): ?array
    {
        return $this->db->queryOne('SELECT ' . self::COLUMNS . ' FROM pricing_config WHERE id = ?', [$id]);
    }

    public function grid(): FurniturePricingGrid
    {
        return FurniturePricingGrid::fromConfigRows($this->db->query('SELECT category, item_type, param_name, param_value FROM pricing_config'));
    }

    public function materialPrices(): MaterialPriceRepository
    {
        return new MaterialPriceRepository($this->db);
    }

    public function create(array $data): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description, is_active)
             VALUES (?, ?, ?, ?, ?, ?, TRUE) RETURNING id',
            [$data['category'], $data['item_type'], $data['param_name'], $data['param_value'], $data['unit'], $data['description']],
        );
    }

    public function findDuplicate(string $category, string $itemType, string $paramName): bool
    {
        return $this->db->queryOne(
            'SELECT id FROM pricing_config WHERE category = ? AND item_type = ? AND param_name = ?',
            [$category, $itemType, $paramName],
        ) !== null;
    }

    public function updateValue(int $id, float $value): void
    {
        $this->db->execute('UPDATE pricing_config SET param_value = ?, is_active = TRUE, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$value, $id]);
    }
}
