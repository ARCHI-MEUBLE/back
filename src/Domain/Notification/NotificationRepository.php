<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Db\Connection;

final class NotificationRepository
{
    public function __construct(private readonly Connection $db) {}

    public function forCustomer(int $customerId, bool $unreadOnly, int $limit): array
    {
        $sql = 'SELECT * FROM notifications WHERE customer_id = ?' . ($unreadOnly ? ' AND is_read = FALSE' : '') . ' ORDER BY created_at DESC LIMIT ?';
        return $this->db->query($sql, [$customerId, $limit]);
    }

    public function countUnread(int $customerId): int
    {
        return (int) ($this->db->scalar('SELECT COUNT(*) FROM notifications WHERE customer_id = ? AND is_read = FALSE', [$customerId]) ?? 0);
    }

    public function markAsRead(int $id, int $customerId): void
    {
        $this->db->execute('UPDATE notifications SET is_read = TRUE WHERE id = ? AND customer_id = ?', [$id, $customerId]);
    }

    public function markAllAsRead(int $customerId): void
    {
        $this->db->execute('UPDATE notifications SET is_read = TRUE WHERE customer_id = ?', [$customerId]);
    }

    public function delete(int $id, int $customerId): void
    {
        $this->db->execute('DELETE FROM notifications WHERE id = ? AND customer_id = ?', [$id, $customerId]);
    }

    public function create(int $customerId, string $type, string $title, string $message, ?int $relatedId, ?string $relatedType): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO notifications (customer_id, type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?) RETURNING id',
            [$customerId, $type, $title, $message, $relatedId, $relatedType],
        );
    }
}
