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
    public function create($adminId, $type, $message, $relatedId = null) {
        $query = "INSERT INTO admin_notifications (admin_id, type, message, related_id)
                  VALUES (?, ?, ?, ?)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$adminId, $type, $message, $relatedId]);

        return $this->db->lastInsertId();
    }

    /**
     * Récupérer les notifications non lues
     */
    public function getUnread($adminId) {
        $query = "SELECT * FROM admin_notifications
                  WHERE admin_id = ? AND is_read = 0
                  ORDER BY created_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$adminId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marquer comme lu
     */
    public function markAsRead($id) {
        $query = "UPDATE admin_notifications SET is_read = 1 WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$id]);
    }

    /**
     * Supprimer une notification
     */
    public function delete($id) {
        $query = "DELETE FROM admin_notifications WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$id]);
    }
}
