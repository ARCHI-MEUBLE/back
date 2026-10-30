<?php

declare(strict_types=1);

namespace App\Domain\Realisation;

use App\Db\Connection;

final class RealisationRepository
{
    private const UPDATABLE_COLUMNS = ['titre', 'description', 'image_url', 'date_projet', 'categorie', 'lieu', 'dimensions'];

    public function __construct(private readonly Connection $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT * FROM realisations ORDER BY created_at DESC');
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM realisations WHERE id = ?', [$id]);
    }

    public function create(array $data): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO realisations (titre, description, image_url, date_projet, categorie, lieu, dimensions)
             VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id',
            [
                $data['titre'], $data['description'] ?? null, $data['image_url'] ?? null, $data['date_projet'] ?? null,
                $data['categorie'] ?? null, $data['lieu'] ?? null, $data['dimensions'] ?? null,
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
        $params[] = $id;
        $this->db->execute('UPDATE realisations SET ' . implode(', ', $columns) . ' WHERE id = ?', $params);
        return true;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM realisations WHERE id = ?', [$id]);
    }
}
