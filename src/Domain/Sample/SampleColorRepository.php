<?php

declare(strict_types=1);

namespace App\Domain\Sample;

use App\Db\Connection;
use App\Domain\Shared\DomainException;

final class SampleColorRepository
{
    private const COLUMNS = 'id, type_id, name, hex, image_url, active, position, price_per_m2, unit_price, created_at, updated_at';

    public function __construct(private readonly Connection $db) {}

    public function forType(int $typeId): array
    {
        return $this->db->query('SELECT ' . self::COLUMNS . ' FROM sample_colors WHERE type_id = ? ORDER BY position, name', [$typeId]);
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT ' . self::COLUMNS . ' FROM sample_colors WHERE id = ?', [$id]);
    }

    public function create(int $typeId, string $name, ?string $hex, ?string $imageUrl, int $position, float $pricePerM2, float $unitPrice): string
    {
        if ($imageUrl !== null && $this->db->queryOne('SELECT id FROM sample_colors WHERE type_id = ? AND image_url = ?', [$typeId, $imageUrl]) !== null) {
            throw new DomainException('Cette image existe déjà pour ce type');
        }
        if ($imageUrl === null && $hex !== null && $this->db->queryOne('SELECT id FROM sample_colors WHERE type_id = ? AND hex = ?', [$typeId, $hex]) !== null) {
            throw new DomainException('Cette couleur existe déjà pour ce type');
        }
        $this->db->execute(
            'INSERT INTO sample_colors (type_id, name, hex, image_url, position, active, price_per_m2, unit_price) VALUES (?, ?, ?, ?, ?, TRUE, ?, ?)',
            [$typeId, $name, $hex, $imageUrl, $position, $pricePerM2, $unitPrice],
        );
        return (string) $this->db->scalar('SELECT lastval()');
    }

    public function update(int $id, array $data): bool
    {
        $color = $this->findById($id);
        if ($color === null) {
            return false;
        }
        $active = (isset($data['active']) && $data['active'] !== '') ? filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) : $color['active'];
        $this->db->execute(
            'UPDATE sample_colors SET name = ?, hex = ?, image_url = ?, active = ?, position = ?, price_per_m2 = ?, unit_price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [
                $data['name'] ?? $color['name'], $data['hex'] ?? $color['hex'], $data['image_url'] ?? $color['image_url'], $active,
                isset($data['position']) ? (int) $data['position'] : $color['position'],
                isset($data['price_per_m2']) ? (float) $data['price_per_m2'] : $color['price_per_m2'],
                isset($data['unit_price']) ? (float) $data['unit_price'] : $color['unit_price'],
                $id,
            ],
        );
        return true;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM sample_colors WHERE id = ?', [$id]);
    }
}
