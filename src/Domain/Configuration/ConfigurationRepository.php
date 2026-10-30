<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Db\Connection;

final class ConfigurationRepository
{
    public function __construct(private readonly Connection $db) {}

    public function findWithOrderId(int $id): ?array
    {
        return $this->db->queryOne(
            'SELECT c.*, oi.order_id FROM configurations c
             LEFT JOIN order_items oi ON c.id = oi.configuration_id
             WHERE c.id = ? ORDER BY oi.id DESC LIMIT 1',
            [$id],
        );
    }

    public function all(): array
    {
        return $this->db->query('SELECT c.* FROM configurations c ORDER BY c.created_at DESC');
    }

    public function findBySession(string $session): array
    {
        return $this->db->query('SELECT c.* FROM configurations c WHERE c.user_session = ? ORDER BY c.created_at DESC', [$session]);
    }

    public function findByCustomerId(int $customerId): array
    {
        return $this->db->query(
            "SELECT c.*,
                    (SELECT MAX(order_id) FROM order_items WHERE configuration_id = c.id) as order_id,
                    (SELECT o.payment_status FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.configuration_id = c.id LIMIT 1) as order_payment_status
             FROM configurations c
             WHERE c.user_id = ?
             AND NOT EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.configuration_id = c.id AND o.payment_status = 'paid')
             ORDER BY c.created_at DESC",
            [(string) $customerId],
        );
    }

    public function countByCustomerId(int $customerId): int
    {
        return (int) ($this->db->scalar('SELECT COUNT(*) FROM configurations WHERE user_id = ?', [(string) $customerId]) ?? 0);
    }

    public function create(?string $userId, ?int $templateId, ?string $configString, float $price, ?string $glbUrl, ?string $prompt, ?string $userSession, string $status): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO configurations (user_id, user_session, template_id, config_string, prompt, price, glb_url, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
            [$userId, $userSession, $templateId, $configString, $prompt, $price, $glbUrl, $status],
        );
    }

    public function update(int $id, array $columns): void
    {
        $set = [];
        $params = [];
        foreach ($columns as $column => $value) {
            $set[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $this->db->execute('UPDATE configurations SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM configurations WHERE id = ?', [$id]);
    }

    public function adminFindById(int $id): ?array
    {
        $row = $this->db->queryOne(
            'SELECT c.*, cust.email as customer_email, cust.first_name as customer_first_name, cust.last_name as customer_last_name, cust.phone as customer_phone, m.name as model_name
             FROM configurations c
             LEFT JOIN customers cust ON CAST(c.user_id AS INTEGER) = cust.id
             LEFT JOIN models m ON c.template_id = m.id
             WHERE c.id = ?',
            [$id],
        );
        return $row === null ? null : self::withCustomerName($row);
    }

    public function adminList(?string $status, int $limit, int $offset): array
    {
        [$where, $params] = self::statusFilter($status);
        $rows = $this->db->query(
            "SELECT c.id, c.user_id, c.template_id as model_id, c.prompt, c.config_string, c.price, c.glb_url, c.dxf_url, c.created_at, c.status,
                    cust.email as customer_email, cust.first_name as customer_first_name, cust.last_name as customer_last_name, cust.phone as customer_phone, m.name as model_name
             FROM configurations c
             LEFT JOIN customers cust ON CAST(c.user_id AS INTEGER) = cust.id
             LEFT JOIN models m ON c.template_id = m.id
             $where ORDER BY c.created_at DESC LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );
        return array_map(self::withConfigName(...), $rows);
    }

    public function adminCount(?string $status): int
    {
        [$where, $params] = self::statusFilter($status);
        return (int) ($this->db->scalar("SELECT COUNT(*) FROM configurations c $where", $params) ?? 0);
    }

    private static function statusFilter(?string $status): array
    {
        return $status === null ? ['', []] : ['WHERE c.status = ?', [$status]];
    }

    private static function withCustomerName(array $row): array
    {
        if (isset($row['customer_first_name'], $row['customer_last_name'])) {
            $row['customer_name'] = trim($row['customer_first_name'] . ' ' . $row['customer_last_name']);
        }
        return $row;
    }

    private static function withConfigName(array $row): array
    {
        $row = self::withCustomerName($row);
        if (isset($row['config_string'])) {
            $decoded = json_decode((string) $row['config_string'], true);
            if (is_array($decoded) && isset($decoded['name'])) {
                $row['name'] = $decoded['name'];
            }
        }
        return $row;
    }
}
