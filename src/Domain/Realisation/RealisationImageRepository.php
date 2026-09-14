<?php

declare(strict_types=1);

namespace App\Domain\Realisation;

use App\Db\Connection;

final class RealisationImageRepository
{
    public function __construct(private readonly Connection $db) {}

    public function forRealisation(int $realisationId): array
    {
        return $this->db->query('SELECT * FROM realisation_images WHERE realisation_id = ? ORDER BY ordre ASC', [$realisationId]);
    }

    public function nextOrder(int $realisationId): int
    {
        return 1 + (int) ($this->db->scalar('SELECT COALESCE(MAX(ordre), 0) FROM realisation_images WHERE realisation_id = ?', [$realisationId]) ?? 0);
    }

    public function create(int $realisationId, string $imageUrl, string $legende, int $ordre): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO realisation_images (realisation_id, image_url, legende, ordre) VALUES (?, ?, ?, ?) RETURNING id',
            [$realisationId, $imageUrl, $legende, $ordre],
        );
    }

    public function update(int $id, array $columns): void
    {
        if ($columns === []) {
            return;
        }
        $set = [];
        $params = [];
        foreach ($columns as $column => $value) {
            $set[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $this->db->execute('UPDATE realisation_images SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM realisation_images WHERE id = ?', [$id]);
    }
}
