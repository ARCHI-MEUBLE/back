<?php

declare(strict_types=1);

namespace App\Domain\Category;

use App\Db\Connection;

final class CategoryRepository
{
    private const UPDATABLE_COLUMNS = ['name', 'slug', 'description', 'image_url', 'display_order', 'is_active'];

    public function __construct(private readonly Connection $db) {}

    public function all(bool $onlyActive): array
    {
        $sql = 'SELECT * FROM categories' . ($onlyActive ? ' WHERE is_active = TRUE' : '') . ' ORDER BY display_order ASC, name ASC';
        return $this->db->query($sql);
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->queryOne('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    public function findByName(string $name): ?array
    {
        return $this->db->queryOne('SELECT * FROM categories WHERE name = ?', [$name]);
    }

    public function create(string $name, string $slug, ?string $description, ?string $imageUrl, int $displayOrder, bool $isActive): ?int
    {
        if ($this->findBySlug($slug) !== null || $this->findByName($name) !== null) {
            return null;
        }
        return $this->db->insertReturningId(
            'INSERT INTO categories (name, slug, description, image_url, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?) RETURNING id',
            [$name, $slug, $description, $imageUrl, $displayOrder, $isActive],
        );
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['slug'])) {
            $existing = $this->findBySlug((string) $data['slug']);
            if ($existing !== null && (int) $existing['id'] !== $id) {
                return false;
            }
        }
        if (isset($data['name'])) {
            $existing = $this->findByName((string) $data['name']);
            if ($existing !== null && (int) $existing['id'] !== $id) {
                return false;
            }
        }
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
        $params[] = $id;
        $this->db->execute('UPDATE categories SET ' . implode(', ', $columns) . ' WHERE id = ?', $params);
        return true;
    }

    public function delete(int $id): bool
    {
        $usageCount = (int) ($this->db->scalar(
            'SELECT COUNT(*) FROM models WHERE category = (SELECT slug FROM categories WHERE id = ?)',
            [$id],
        ) ?? 0);
        if ($usageCount > 0) {
            return false;
        }
        $this->db->execute('DELETE FROM categories WHERE id = ?', [$id]);
        return true;
    }

    public function reorder(array $ids): void
    {
        $this->db->transaction(function (Connection $db) use ($ids): void {
            foreach (array_values($ids) as $index => $id) {
                $db->execute('UPDATE categories SET display_order = ? WHERE id = ?', [$index + 1, (int) $id]);
            }
        });
    }
}
