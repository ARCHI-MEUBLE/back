<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Db\Connection;

final class CatalogueVariationRepository
{
    public function __construct(private readonly Connection $db) {}

    public function forItem(int $itemId): array
    {
        return $this->db->query(
            'SELECT id, catalogue_item_id, color_name, image_url, is_default FROM catalogue_item_variations WHERE catalogue_item_id = ? ORDER BY is_default DESC, id ASC',
            [$itemId],
        );
    }

    public function forItems(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        return $this->db->query(
            "SELECT id, catalogue_item_id, color_name, image_url, is_default FROM catalogue_item_variations WHERE catalogue_item_id IN ($placeholders) ORDER BY is_default DESC, id ASC",
            $itemIds,
        );
    }

    public function existsForColor(int $itemId, string $colorName): bool
    {
        return $this->db->queryOne('SELECT id FROM catalogue_item_variations WHERE catalogue_item_id = ? AND color_name = ?', [$itemId, $colorName]) !== null;
    }

    public function create(int $itemId, string $colorName, string $imageUrl, bool $isDefault): string
    {
        $this->db->execute(
            'INSERT INTO catalogue_item_variations (catalogue_item_id, color_name, image_url, is_default) VALUES (?, ?, ?, ?)',
            [$itemId, $colorName, $imageUrl, $isDefault],
        );
        return (string) $this->db->scalar('SELECT lastval()');
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM catalogue_item_variations WHERE id = ?', [$id]) > 0;
    }
}
