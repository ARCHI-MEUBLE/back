<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Db\Connection;

final class FacadeMaterialRepository
{
    public function __construct(private readonly Connection $db) {}

    public function all(bool $activeOnly): array
    {
        return $this->db->query('SELECT * FROM facade_materials' . ($activeOnly ? ' WHERE is_active = TRUE' : '') . ' ORDER BY name ASC');
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM facade_materials WHERE id = ?', [$id]);
    }

    public function create(array $values): string
    {
        $this->db->execute(
            'INSERT INTO facade_materials (name, color_hex, texture_url, price_modifier, price_per_m2, is_active) VALUES (?, ?, ?, ?, ?, ?)',
            $values,
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
        $this->db->execute('UPDATE facade_materials SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM facade_materials WHERE id = ?', [$id]);
    }
}
