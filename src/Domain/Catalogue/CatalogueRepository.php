<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Db\Connection;

final class CatalogueRepository
{
    private const PUBLIC_COLUMNS = 'id, name, category, description, material, dimensions, unit_price, unit, min_order_quantity, image_url, weight, tags, variation_label';

    public function __construct(private readonly Connection $db) {}

    public function publicList(?string $category, ?string $search, int $limit, int $offset): array
    {
        [$where, $params] = self::filters($category, $search, publicOnly: true);
        $sql = 'SELECT ' . self::PUBLIC_COLUMNS . " FROM catalogue_items WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return $this->db->query($sql, [...$params, $limit, $offset]);
    }

    public function publicCount(?string $category, ?string $search): int
    {
        [$where, $params] = self::filters($category, $search, publicOnly: true);
        return (int) ($this->db->scalar("SELECT COUNT(*) FROM catalogue_items WHERE $where", $params) ?? 0);
    }

    public function findPublicById(int $id): ?array
    {
        return $this->db->queryOne('SELECT ' . self::PUBLIC_COLUMNS . ' FROM catalogue_items WHERE id = ? AND is_available = TRUE', [$id]);
    }

    public function publicCategories(): array
    {
        return array_column($this->db->query('SELECT DISTINCT category FROM catalogue_items WHERE is_available = TRUE ORDER BY category'), 'category');
    }

    public function adminCategories(): array
    {
        $existing = array_column($this->db->query("SELECT DISTINCT category FROM catalogue_items WHERE category IS NOT NULL AND category != '' ORDER BY category"), 'category');
        return array_values(array_unique(array_merge(['Portes', 'Planches à découper'], $existing)));
    }

    public function adminList(?string $category, ?string $search, ?string $available): array
    {
        $where = ['1=1'];
        $params = [];
        if ($category !== null) {
            $where[] = 'category = ?';
            $params[] = $category;
        }
        if ($search !== null) {
            $where[] = '(name LIKE ? OR description LIKE ? OR tags LIKE ?)';
            array_push($params, "%$search%", "%$search%", "%$search%");
        }
        if ($available !== null) {
            $where[] = 'is_available = ?';
            $params[] = (int) $available;
        }
        return $this->db->query('SELECT * FROM catalogue_items WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC', $params);
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM catalogue_items WHERE id = ?', [$id]);
    }

    public function create(array $values): string
    {
        $this->db->execute(
            'INSERT INTO catalogue_items (name, category, description, material, dimensions, unit_price, unit, stock_quantity, min_order_quantity, is_available, image_url, weight, tags, variation_label)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $values,
        );
        return (string) $this->db->scalar('SELECT lastval()');
    }

    public function update(int $id, array $values): void
    {
        $this->db->execute(
            'UPDATE catalogue_items SET name = ?, category = ?, description = ?, material = ?, dimensions = ?, unit_price = ?, unit = ?,
             stock_quantity = ?, min_order_quantity = ?, is_available = ?, image_url = ?, weight = ?, tags = ?, variation_label = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?',
            [...$values, $id],
        );
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM catalogue_items WHERE id = ?', [$id]) > 0;
    }

    private static function filters(?string $category, ?string $search, bool $publicOnly): array
    {
        $where = $publicOnly ? ['is_available = TRUE'] : ['1=1'];
        $params = [];
        if ($category !== null) {
            $where[] = 'category = ?';
            $params[] = $category;
        }
        if ($search !== null) {
            $where[] = '(name LIKE ? OR description LIKE ? OR tags LIKE ?)';
            array_push($params, "%$search%", "%$search%", "%$search%");
        }
        return [implode(' AND ', $where), $params];
    }
}
