<?php

declare(strict_types=1);

namespace App\Domain\Review;

use App\Db\Connection;

final class ReviewRepository
{
    public function __construct(private readonly Connection $db) {}

    public function publicList(): array
    {
        return $this->db->query('SELECT id, user_id, author_name, rating, text, date, created_at FROM avis ORDER BY created_at DESC');
    }

    public function adminList(): array
    {
        return $this->db->query('SELECT * FROM avis ORDER BY created_at DESC');
    }

    public function exists(int $id): bool
    {
        return $this->db->queryOne('SELECT id FROM avis WHERE id = ?', [$id]) !== null;
    }

    public function create(?string $userId, string $authorName, int $rating, string $text, string $date): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO avis (user_id, author_name, rating, text, date) VALUES (?, ?, ?, ?, ?) RETURNING id',
            [$userId, $authorName, $rating, $text, $date],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM avis WHERE id = ?', [$id]);
    }
}
