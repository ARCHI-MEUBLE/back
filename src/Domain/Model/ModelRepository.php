<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Db\Connection;

final class ModelRepository
{
    private const UPDATABLE_COLUMNS = ['name', 'description', 'prompt', 'price', 'image_url', 'category', 'config_data', 'hover_image_url'];

    public function __construct(private readonly Connection $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT * FROM models ORDER BY created_at DESC');
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM models WHERE id = ?', [$id]);
    }

    public function namesByPromptPrefix(): array
    {
        return $this->db->query('SELECT name, prompt FROM models ORDER BY id');
    }

    public function create(array $data): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO models (name, description, prompt, price, image_url, category, config_data, hover_image_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
            [
                $data['name'], $data['description'], $data['prompt'], $data['price'], $data['image_url'],
                $data['category'], $data['config_data'], $data['hover_image_url'],
            ],
        );
    }

    public function update(int $id, array $data): bool
    {
        $columns = [];
        $params = [];
        foreach (self::UPDATABLE_COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $columns[] = $column . ' = ?';
                $params[] = $data[$column];
            }
        }
        if ($columns === []) {
            return false;
        }
        $columns[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;
        $this->db->execute('UPDATE models SET ' . implode(', ', $columns) . ' WHERE id = ?', $params);
        return true;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM models WHERE id = ?', [$id]);
    }
}
