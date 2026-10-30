<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Db\Connection;

final class PricingConfigRepository
{
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

    public function exists(int $id): bool
    {
        return $this->db->queryOne('SELECT id FROM pricing_config WHERE id = ?', [$id]) !== null;
    }

    public function create(array $data): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id',
            [$data['category'], $data['item_type'], $data['param_name'], $data['param_value'], $data['unit'], $data['description'], $data['is_active']],
        );
    }

    public function findDuplicate(string $category, string $itemType, string $paramName): bool
    {
        return $this->db->queryOne(
            'SELECT id FROM pricing_config WHERE category = ? AND item_type = ? AND param_name = ?',
            [$category, $itemType, $paramName],
        ) !== null;
    }

    public function update(int $id, array $columns): void
    {
        $set = [];
        $params = [];
        foreach ($columns as $column => $value) {
            $set[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $this->db->execute('UPDATE pricing_config SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

    public function deactivate(int $id): bool
    {
        return $this->db->execute('UPDATE pricing_config SET is_active = FALSE WHERE id = ?', [$id]) > 0;
    }
}
