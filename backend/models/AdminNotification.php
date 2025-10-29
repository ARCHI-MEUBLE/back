<?php
/**
 * Modèle AdminNotification - Gestion des notifications admin
 */

require_once __DIR__ . '/../core/Database.php';

class AdminNotification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Créer une notification
     */
    public function create($type, $title, $message, $relatedOrderId = null) {
        $sql = "INSERT INTO admin_notifications (type, title, message, related_order_id, created_at)
                VALUES (?, ?, ?, ?, datetime('now'))";
        
        return $this->db->execute($sql, [$type, $title, $message, $relatedOrderId]);
    }

    /**
     * Récupérer les notifications non lues
     */
    public function getUnread($limit = 50) {
        $sql = "SELECT * FROM admin_notifications
                WHERE is_read = 0
                ORDER BY created_at DESC
                LIMIT ?";
        
        return $this->db->query($sql, [$limit]);
    }

    /**
     * Récupérer toutes les notifications
     */
    public function getAll($limit = 100, $offset = 0) {
        $sql = "SELECT * FROM admin_notifications
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        
        return $this->db->query($sql, [$limit, $offset]);
    }

    /**
     * Compter les notifications non lues
     */
    public function countUnread() {
        $sql = "SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0";
        $result = $this->db->queryOne($sql);
        return $result ? (int)$result['count'] : 0;
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead() {
        $sql = "UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0";
        return $this->db->execute($sql);
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead($id) {
        $sql = "UPDATE admin_notifications SET is_read = 1 WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }

    /**
     * Supprimer une notification
     */
    public function delete($id) {
        $sql = "DELETE FROM admin_notifications WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
}
