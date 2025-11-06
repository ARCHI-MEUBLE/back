<?php
/**
 * Service de gestion des mensualités
 * Gère la création et le traitement des paiements en 3 fois
 */

require_once __DIR__ . '/../core/Database.php';

class InstallmentService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crée les 3 mensualités pour un paiement fractionné
     */
    public function createInstallments($orderId, $customerId, $totalAmount) {
        $installmentAmount = $totalAmount / 3;

        // Créer les 3 mensualités
        for ($i = 1; $i <= 3; $i++) {
            $dueDate = date('Y-m-d', strtotime("+{$i} month", strtotime('today')));

            // La première mensualité est déjà payée
            $status = ($i === 1) ? 'paid' : 'pending';

            $query = "INSERT INTO payment_installments
                      (order_id, customer_id, installment_number, amount, due_date, status, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

            $this->db->execute($query, [
                $orderId,
                $customerId,
                $i,
                $installmentAmount,
                $dueDate,
                $status
            ]);
        }

        return true;
    }

    /**
     * Récupère les mensualités d'une commande
     */
    public function getInstallmentsByOrder($orderId) {
        $query = "SELECT * FROM payment_installments WHERE order_id = ? ORDER BY installment_number ASC";
        return $this->db->query($query, [$orderId]);
    }

    /**
     * Récupère les mensualités à prélever (dues et non payées)
     */
    public function getPendingInstallments() {
        $query = "SELECT pi.*,
                         o.order_number,
                         o.stripe_payment_intent_id as order_payment_intent,
                         c.first_name,
                         c.last_name,
                         c.email,
                         c.stripe_customer_id
                  FROM payment_installments pi
                  JOIN orders o ON pi.order_id = o.id
                  JOIN customers c ON pi.customer_id = c.id
                  WHERE pi.status = 'pending'
                    AND pi.due_date <= DATE('now')
                    AND c.stripe_customer_id IS NOT NULL
                  ORDER BY pi.due_date ASC";

        return $this->db->query($query);
    }

    /**
     * Marque une mensualité comme payée
     */
    public function markInstallmentPaid($installmentId, $stripePaymentIntentId) {
        $query = "UPDATE payment_installments
                  SET status = 'paid',
                      stripe_payment_intent_id = ?,
                      paid_at = CURRENT_TIMESTAMP,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = ?";

        return $this->db->execute($query, [$stripePaymentIntentId, $installmentId]);
    }

    /**
     * Marque une mensualité comme échouée
     */
    public function markInstallmentFailed($installmentId) {
        $query = "UPDATE payment_installments
                  SET status = 'failed',
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = ?";

        return $this->db->execute($query, [$installmentId]);
    }

    /**
     * Récupère les mensualités d'un client
     */
    public function getCustomerInstallments($customerId) {
        $query = "SELECT pi.*, o.order_number
                  FROM payment_installments pi
                  JOIN orders o ON pi.order_id = o.id
                  WHERE pi.customer_id = ?
                  ORDER BY pi.due_date DESC";

        return $this->db->query($query, [$customerId]);
    }

    /**
     * Vérifie si une commande a un paiement en 3 fois
     */
    public function hasInstallments($orderId) {
        $query = "SELECT COUNT(*) as count FROM payment_installments WHERE order_id = ?";
        $result = $this->db->queryOne($query, [$orderId]);

        return $result && $result['count'] > 0;
    }

    /**
     * Récupère le nombre de mensualités impayées d'un client
     */
    public function getUnpaidInstallmentsCount($customerId) {
        $query = "SELECT COUNT(*) as count
                  FROM payment_installments
                  WHERE customer_id = ? AND status = 'pending' AND due_date <= DATE('now')";

        $result = $this->db->queryOne($query, [$customerId]);
        return $result ? (int)$result['count'] : 0;
    }
}
