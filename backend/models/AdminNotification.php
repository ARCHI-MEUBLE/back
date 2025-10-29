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
     * Créer une notification pour tous les admins
     * @param string $type Type de notification (order, config, user, etc.)
     * @param string $message Message de la notification
     * @param int|null $relatedId ID de l'entité liée (commande, config, etc.)
     * @return bool
     */
    public function create($type, $message, $relatedId = null) {
        // Récupérer tous les admins
        $admins = $this->db->query("SELECT id FROM admins");

        if (empty($admins)) {
            return false;
        }

        // Créer une notification pour chaque admin
        $sql = "INSERT INTO admin_notifications (admin_id, type, message, related_id)
                VALUES (?, ?, ?, ?)";

        $success = true;
        foreach ($admins as $admin) {
            $result = $this->db->execute($sql, [$admin['id'], $type, $message, $relatedId]);
            $success = $success && $result;
        }

        return $success;
    }

    /**
     * Récupérer les notifications non lues pour un admin spécifique
     * @param int $adminId ID de l'admin
     * @param int $limit Nombre max de résultats
     * @return array
     */
    public function getUnread($adminId, $limit = 50) {
        $sql = "SELECT * FROM admin_notifications
                WHERE admin_id = ? AND is_read = 0
                ORDER BY created_at DESC
                LIMIT ?";

        return $this->db->query($sql, [$adminId, $limit]);
    }

    /**
     * Récupérer toutes les notifications pour un admin spécifique
     * @param int $adminId ID de l'admin
     * @param int $limit Nombre max de résultats
     * @param int $offset Offset pour la pagination
     * @return array
     */
    public function getAll($adminId, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM admin_notifications
                WHERE admin_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";

        return $this->db->query($sql, [$adminId, $limit, $offset]);
    }

    /**
     * Compter les notifications non lues pour un admin spécifique
     * @param int $adminId ID de l'admin
     * @return int
     */
    public function countUnread($adminId) {
        $sql = "SELECT COUNT(*) as count FROM admin_notifications WHERE admin_id = ? AND is_read = 0";
        $result = $this->db->queryOne($sql, [$adminId]);
        return $result ? (int)$result['count'] : 0;
    }

    /**
     * Marquer toutes les notifications comme lues pour un admin spécifique
     * @param int $adminId ID de l'admin
     * @return bool
     */
    public function markAllAsRead($adminId) {
        $sql = "UPDATE admin_notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0";
        return $this->db->execute($sql, [$adminId]);
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
