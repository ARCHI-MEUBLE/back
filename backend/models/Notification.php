<?php
/**
 * ArchiMeuble - Modèle Notification
 * Gère les notifications des clients
 * Auteur : Claude Code
 * Date : 2025-10-29
 */

require_once __DIR__ . '/../core/Database.php';

class Notification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crée une notification
     * @param int $customerId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param int|null $relatedId
     * @param string|null $relatedType
     * @return int
     */
    public function create($customerId, $type, $title, $message, $relatedId = null, $relatedType = null) {
        $query = "INSERT INTO notifications (customer_id, type, title, message, related_id, related_type)
                  VALUES (:customer_id, :type, :title, :message, :related_id, :related_type)";

        $params = [
            'customer_id' => $customerId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_id' => $relatedId,
            'related_type' => $relatedType
        ];

        $this->db->execute($query, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Récupère les notifications d'un client
     * @param int $customerId
     * @param bool $unreadOnly
     * @param int $limit
     * @return array
     */
    public function getByCustomer($customerId, $unreadOnly = false, $limit = 50) {
        $query = "SELECT * FROM notifications WHERE customer_id = :customer_id";

        if ($unreadOnly) {
            $query .= " AND is_read = FALSE";
        }

        $query .= " ORDER BY created_at DESC LIMIT :limit";

        $params = [
            'customer_id' => $customerId,
            'limit' => $limit
        ];

        return $this->db->query($query, $params);
    }

    /**
     * Compte les notifications non lues
     * @param int $customerId
     * @return int
     */
    public function countUnread($customerId) {
        $query = "SELECT COUNT(*) as count FROM notifications
                  WHERE customer_id = :customer_id AND is_read = FALSE";

        $result = $this->db->queryOne($query, ['customer_id' => $customerId]);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Marque une notification comme lue
     * @param int $id
     * @param int $customerId
     * @return bool
     */
    public function markAsRead($id, $customerId) {
        $query = "UPDATE notifications SET is_read = TRUE
                  WHERE id = :id AND customer_id = :customer_id";

        $this->db->execute($query, [
            'id' => $id,
            'customer_id' => $customerId
        ]);

        return true;
    }

    /**
     * Marque toutes les notifications comme lues
     * @param int $customerId
     * @return bool
     */
    public function markAllAsRead($customerId) {
        $query = "UPDATE notifications SET is_read = TRUE WHERE customer_id = :customer_id";

        $this->db->execute($query, ['customer_id' => $customerId]);

        return true;
    }

    /**
     * Supprime une notification
     * @param int $id
     * @param int $customerId
     * @return bool
     */
    public function delete($id, $customerId) {
        $query = "DELETE FROM notifications WHERE id = :id AND customer_id = :customer_id";

        $this->db->execute($query, [
            'id' => $id,
            'customer_id' => $customerId
        ]);

        return true;
    }

    /**
     * Crée une notification de changement de statut de commande
     * @param int $customerId
     * @param int $orderId
     * @param string $orderNumber
     * @param string $newStatus
     * @return int
     */
    public function createOrderStatusNotification($customerId, $orderId, $orderNumber, $newStatus) {
        $statusLabels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'in_production' => 'En production',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée'
        ];

        $statusLabel = $statusLabels[$newStatus] ?? $newStatus;

        $title = "Commande #{$orderNumber} - Mise à jour";
        $message = "Le statut de votre commande a été mis à jour : {$statusLabel}";

        return $this->create(
            $customerId,
            'order_status',
            $title,
            $message,
            $orderId,
            'order'
        );
    }
}
