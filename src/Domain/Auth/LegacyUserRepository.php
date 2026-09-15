<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Db\Connection;

final class LegacyUserRepository
{
    private const UPDATABLE_COLUMNS = ['email', 'name', 'updated_at'];

    public function __construct(private readonly Connection $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT id, email, name, created_at FROM users ORDER BY created_at DESC');
    }

    public function emailExists(string $email): bool
    {
        return (int) ($this->db->scalar('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) ?? 0) > 0;
    }

    public function verifyCredentials(string $email, string $password): ?LegacyUserAccount
    {
        $row = $this->db->queryOne('SELECT * FROM users WHERE email = ?', [$email]);
        if ($row === null || !is_string($row['password_hash'] ?? null) || !password_verify($password, $row['password_hash'])) {
            return null;
        }
        return LegacyUserAccount::fromRow($row);
    }

    public function create(string $id, string $email, string $passwordHash, ?string $name): void
    {
        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, name) VALUES (?, ?, ?, ?)',
            [$id, $email, $passwordHash, $name],
        );
    }

    public function applyWhitelistedUpdate(string $id, array $data): int
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
        return $this->db->execute('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public function updatePasswordHash(string $id, string $passwordHash): void
    {
        $this->db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    public function delete(string $id): bool
    {
        return $this->db->execute('DELETE FROM users WHERE id = ?', [$id]) > 0;
    }
}
