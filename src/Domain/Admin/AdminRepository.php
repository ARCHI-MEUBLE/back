<?php

declare(strict_types=1);

namespace App\Domain\Admin;

use App\Db\Connection;

final class AdminRepository
{
    private const UPDATABLE_COLUMNS = ['username', 'email', 'updated_at'];

    public function __construct(private readonly Connection $db) {}

    public function all(): array
    {
        return array_map(
            AdminAccount::fromRow(...),
            $this->db->query('SELECT id, username, email, created_at FROM admins ORDER BY created_at DESC'),
        );
    }

    public function findByEmail(string $email): ?AdminAccount
    {
        $row = $this->db->queryOne('SELECT id, username, email, created_at FROM admins WHERE email = ? OR username = ?', [$email, $email]);
        return $row === null ? null : AdminAccount::fromRow($row);
    }

    public function findById(int $id): ?AdminAccount
    {
        $row = $this->db->queryOne('SELECT id, username, email, created_at FROM admins WHERE id = ?', [$id]);
        return $row === null ? null : AdminAccount::fromRow($row);
    }

    public function verifyCredentials(string $email, string $password): ?AdminAccount
    {
        $row = $this->db->queryOne('SELECT * FROM admins WHERE email = ? OR username = ?', [$email, $email]);
        if ($row === null || !is_string($row['password_hash'] ?? null) || !password_verify($password, $row['password_hash'])) {
            return null;
        }
        return AdminAccount::fromRow($row);
    }

    public function create(string $email, string $passwordHash, ?string $username = null): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO admins (username, email, password, password_hash) VALUES (?, ?, ?, ?) RETURNING id',
            [$username ?? explode('@', $email)[0], $email, $passwordHash, $passwordHash],
        );
    }

    public function upsertByEmail(string $email, string $passwordHash, ?string $username = null): string
    {
        $existing = $this->findByEmail($email);
        if ($existing === null) {
            $this->create($email, $passwordHash, $username);
            return 'created';
        }
        $this->updatePasswordHash($existing->id, $passwordHash);
        return 'updated';
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $this->db->execute('UPDATE admins SET password = ?, password_hash = ? WHERE id = ?', [$passwordHash, $passwordHash, $id]);
    }

    public function applyWhitelistedUpdate(int $id, array $data): int
    {
        $fields = [];
        $params = [];
        foreach ($data as $column => $value) {
            if (!in_array($column, self::UPDATABLE_COLUMNS, true)) {
                continue;
            }
            $fields[] = $column . ' = ?';
            $params[] = $value;
        }
        if ($fields === []) {
            return 0;
        }
        $params[] = $id;
        return $this->db->execute('UPDATE admins SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM admins WHERE id = ?', [$id]) > 0;
    }

    public function count(): int
    {
        return (int) ($this->db->scalar('SELECT COUNT(*) FROM admins') ?? 0);
    }
}
