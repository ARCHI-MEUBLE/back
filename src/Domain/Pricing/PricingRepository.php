<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Db\Connection;

final class PricingRepository
{
    public function __construct(private readonly Connection $db) {}

    public function active(): array
    {
        return $this->db->query('SELECT * FROM pricing WHERE is_active = TRUE ORDER BY price_per_m3 ASC');
    }

    public function findActiveByName(string $name): ?array
    {
        return $this->db->queryOne('SELECT * FROM pricing WHERE name = ? AND is_active = TRUE', [$name]);
    }

    public function firstActive(): ?array
    {
        return $this->db->queryOne('SELECT * FROM pricing WHERE is_active = TRUE ORDER BY id ASC LIMIT 1');
    }

    public function create(string $name, string $description, float $pricePerM3, bool $isActive): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO pricing (name, description, price_per_m3, is_active) VALUES (?, ?, ?, ?) RETURNING id',
            [$name, $description, $pricePerM3, $isActive],
        );
    }

    public function update(int $id, array $data): void
    {
        $columns = [];
        $params = [];
        foreach (['name', 'description', 'price_per_m3', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $columns[] = $field . ' = ?';
                $params[] = $data[$field];
            }
        }
        $columns[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;
        $this->db->execute('UPDATE pricing SET ' . implode(', ', $columns) . ' WHERE id = ?', $params);
    }

    public function deactivate(int $id): void
    {
        $this->db->execute('UPDATE pricing SET is_active = FALSE WHERE id = ?', [$id]);
    }
}
