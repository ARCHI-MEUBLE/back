<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Db\Connection;

final class FacadeRepository
{
    public function __construct(private readonly Connection $db) {}

    public function all(bool $activeOnly): array
    {
        return $this->db->query('SELECT * FROM facades' . ($activeOnly ? ' WHERE is_active = TRUE' : '') . ' ORDER BY created_at DESC');
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM facades WHERE id = ?', [$id]);
    }

    public function create(array $values): string
    {
        $this->db->execute(
            'INSERT INTO facades (name, description, width, height, depth, base_price, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
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
        $set[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;
        $this->db->execute('UPDATE facades SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM facades WHERE id = ?', [$id]);
    }
}
