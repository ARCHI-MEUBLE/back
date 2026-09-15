<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Db\Connection;

final class FacadeDrillingTypeRepository
{
    public function __construct(private readonly Connection $db) {}

    public function all(bool $activeOnly): array
    {
        return $this->db->query('SELECT * FROM facade_drilling_types' . ($activeOnly ? ' WHERE is_active = TRUE' : '') . ' ORDER BY name ASC');
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM facade_drilling_types WHERE id = ?', [$id]);
    }

    public function create(string $name, string $description, string $iconSvg, float $price, bool $isActive): string
    {
        $this->db->execute(
            'INSERT INTO facade_drilling_types (name, description, icon_svg, price, is_active) VALUES (?, ?, ?, ?, ?)',
            [$name, $description, $iconSvg, $price, $isActive],
        );
        return (string) $this->db->scalar('SELECT lastval()');
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
        $this->db->execute('UPDATE facade_drilling_types SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM facade_drilling_types WHERE id = ?', [$id]);
    }
}
