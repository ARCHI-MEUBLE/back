<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Db\Connection;

final class AdminNotificationRepository
{
    public function __construct(private readonly Connection $db) {}

    public function notifyAllAdmins(string $type, string $message, ?int $relatedId = null): void
    {
        $adminIds = array_column($this->db->query('SELECT id FROM admins'), 'id');
        foreach ($adminIds as $adminId) {
            $this->db->execute(
                'INSERT INTO admin_notifications (admin_id, type, message, related_id) VALUES (?, ?, ?, ?)',
                [$adminId, $type, $message, $relatedId],
            );
        }
    }

    public function unread(int $adminId, int $limit): array
    {
        return $this->db->query('SELECT * FROM admin_notifications WHERE admin_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT ?', [$adminId, $limit]);
    }

    public function all(int $adminId, int $limit, int $offset): array
    {
        return $this->db->query('SELECT * FROM admin_notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?', [$adminId, $limit, $offset]);
    }

    public function countUnread(int $adminId): int
    {
        return (int) ($this->db->scalar('SELECT COUNT(*) FROM admin_notifications WHERE admin_id = ? AND is_read = FALSE', [$adminId]) ?? 0);
    }

    public function markAllAsRead(int $adminId): void
    {
        $this->db->execute('UPDATE admin_notifications SET is_read = TRUE WHERE admin_id = ? AND is_read = FALSE', [$adminId]);
    }

    public function markAsRead(int $id): void
    {
        $this->db->execute('UPDATE admin_notifications SET is_read = TRUE WHERE id = ?', [$id]);
    }
}
