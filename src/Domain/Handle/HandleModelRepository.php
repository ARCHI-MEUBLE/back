<?php

declare(strict_types=1);

namespace App\Domain\Handle;

use App\Db\Connection;

final class HandleModelRepository
{
    private const PUBLIC_COLUMNS = 'id, name, shape, finish_hex, length_mm, center_distance_mm, width_mm, projection_mm, image_url, position';
    private const DIMENSIONS = ['length_mm', 'center_distance_mm', 'width_mm', 'projection_mm'];
    private const ADMIN_COLUMNS = self::PUBLIC_COLUMNS . ', price_per_unit, active, created_at, updated_at';
    private const ORDER = ' ORDER BY position, name, id';

    public function __construct(private readonly Connection $db) {}

    public function active(): array
    {
        return array_map(self::row(...), $this->db->query('SELECT ' . self::PUBLIC_COLUMNS . ' FROM handle_models WHERE active = TRUE' . self::ORDER));
    }

    public function all(): array
    {
        return array_map(self::row(...), $this->db->query('SELECT ' . self::ADMIN_COLUMNS . ' FROM handle_models' . self::ORDER));
    }

    public function activePrices(): array
    {
        return $this->db->query('SELECT id, price_per_unit, shape FROM handle_models WHERE active = TRUE' . self::ORDER);
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->queryOne('SELECT ' . self::ADMIN_COLUMNS . ' FROM handle_models WHERE id = ?', [$id]);
        return $row === null ? null : self::row($row);
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        return $this->db->queryOne('SELECT id FROM handle_models WHERE LOWER(name) = LOWER(?) AND id <> ?', [$name, $exceptId ?? 0]) !== null;
    }

    public function nextPosition(): int
    {
        return (int) $this->db->scalar('SELECT COALESCE(MAX(position), 0) + 1 FROM handle_models');
    }

    public function create(array $data): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO handle_models (name, shape, finish_hex, length_mm, center_distance_mm, width_mm, projection_mm, image_url,
             price_per_unit, active, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
            self::values($data),
        );
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->execute(
            'UPDATE handle_models SET name = ?, shape = ?, finish_hex = ?, length_mm = ?, center_distance_mm = ?, width_mm = ?,
             projection_mm = ?, image_url = ?, price_per_unit = ?, active = ?, position = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [...self::values($data), $id],
        ) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM handle_models WHERE id = ?', [$id]) > 0;
    }

    private static function values(array $data): array
    {
        return [
            $data['name'], $data['shape'], $data['finish_hex'], $data['length_mm'], $data['center_distance_mm'],
            $data['width_mm'], $data['projection_mm'], $data['image_url'], $data['price_per_unit'], $data['active'], $data['position'],
        ];
    }

    private static function row(array $row): array
    {
        $row['id'] = (int) $row['id'];
        foreach (self::DIMENSIONS as $column) {
            $row[$column] = $row[$column] === null ? null : (int) $row[$column];
        }
        $row['position'] = (int) $row['position'];
        if (array_key_exists('active', $row)) {
            $row['active'] = (bool) $row['active'];
        }
        if (array_key_exists('price_per_unit', $row)) {
            $row['price_per_unit'] = (string) $row['price_per_unit'];
        }
        return $row;
    }
}
