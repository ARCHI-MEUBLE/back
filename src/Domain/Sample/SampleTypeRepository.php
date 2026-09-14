<?php

declare(strict_types=1);

namespace App\Domain\Sample;

use App\Db\Connection;

final class SampleTypeRepository
{
    private const COLUMNS = 'id, name, material, description, active, position, price_per_m2, unit_price, created_at, updated_at';

    public function __construct(private readonly Connection $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT ' . self::COLUMNS . ' FROM sample_types ORDER BY material, position, name');
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT ' . self::COLUMNS . ' FROM sample_types WHERE id = ?', [$id]);
    }

    public function create(string $name, string $material, ?string $description, int $position): string
    {
        $this->db->execute(
            'INSERT INTO sample_types (name, material, description, position, active) VALUES (?, ?, ?, ?, TRUE)',
            [$name, $material, $description, $position],
        );
        return (string) $this->db->scalar('SELECT lastval()');
    }

    public function update(int $id, array $data): bool
    {
        $type = $this->findById($id);
        if ($type === null) {
            return false;
        }
        $active = (isset($data['active']) && $data['active'] !== '') ? filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) : $type['active'];
        $this->db->execute(
            'UPDATE sample_types SET name = ?, material = ?, description = ?, active = ?, position = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [
                $data['name'] ?? $type['name'], $data['material'] ?? $type['material'],
                $data['description'] ?? $type['description'], $active,
                isset($data['position']) ? (int) $data['position'] : $type['position'], $id,
            ],
        );
        return true;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM sample_types WHERE id = ?', [$id]);
    }
}
