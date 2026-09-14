<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Db\Connection;

final class CustomerRepository
{
    private const FULL_COLUMNS = 'id, email, first_name, last_name, phone, address, city, postal_code, country, stripe_customer_id, created_at';
    private const AUTH_COLUMNS = 'id, email, first_name, last_name, phone, address, city, postal_code, country, created_at';
    private const BASIC_COLUMNS = 'id, email, first_name, last_name, phone, address, created_at';
    private const UPDATABLE_BASIC = ['first_name', 'last_name', 'email', 'phone', 'address'];

    public function __construct(private readonly Connection $db) {}

    public function create(array $data): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO customers (email, password_hash, first_name, last_name, phone, address, city, postal_code, country)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
            [
                $data['email'], password_hash($data['password'], PASSWORD_BCRYPT), $data['first_name'], $data['last_name'],
                $data['phone'] ?? null, $data['address'] ?? null, $data['city'] ?? null, $data['postal_code'] ?? null,
                $data['country'] ?? 'France',
            ],
        );
    }

    public function findFullById(int $id): ?array
    {
        return $this->db->queryOne('SELECT ' . self::FULL_COLUMNS . ' FROM customers WHERE id = ?', [$id]);
    }

    public function findRawById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    public function findBasicById(int $id): ?array
    {
        return $this->db->queryOne('SELECT ' . self::BASIC_COLUMNS . ' FROM customers WHERE id = ?', [$id]);
    }

    public function findAuthRowByEmail(string $email): ?array
    {
        return $this->db->queryOne('SELECT * FROM customers WHERE email = ?', [$email]);
    }

    public function emailExists(string $email): bool
    {
        return (int) ($this->db->scalar('SELECT COUNT(*) FROM customers WHERE email = ?', [$email]) ?? 0) > 0;
    }

    public function emailUsedByAnother(string $email, int $excludingId): bool
    {
        return $this->db->queryOne('SELECT id FROM customers WHERE email = ? AND id != ?', [$email, $excludingId]) !== null;
    }

    public function verifyCredentials(string $email, string $password): ?array
    {
        $row = $this->findAuthRowByEmail($email);
        if ($row === null || !is_string($row['password_hash'] ?? null) || !password_verify($password, $row['password_hash'])) {
            return null;
        }
        return $this->db->queryOne('SELECT ' . self::AUTH_COLUMNS . ' FROM customers WHERE id = ?', [$row['id']]);
    }

    public function verifyPassword(int $id, string $password): bool
    {
        $hash = $this->db->scalar('SELECT password_hash FROM customers WHERE id = ?', [$id]);
        return is_string($hash) && password_verify($password, $hash);
    }

    public function updateBasic(int $id, array $fields): array
    {
        $columns = [];
        $params = [];
        foreach (self::UPDATABLE_BASIC as $column) {
            if (array_key_exists($column, $fields)) {
                $columns[] = $column . ' = ?';
                $params[] = $fields[$column];
            }
        }
        $params[] = $id;
        $this->db->execute('UPDATE customers SET ' . implode(', ', $columns) . ' WHERE id = ?', $params);
        return (array) $this->findBasicById($id);
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $this->db->execute('UPDATE customers SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$passwordHash, $id]);
    }

    public function markEmailVerified(string $email): void
    {
        $this->db->execute('UPDATE customers SET email_verified = TRUE WHERE email = ?', [$email]);
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM customers WHERE id = ?', [$id]) > 0;
    }
}
