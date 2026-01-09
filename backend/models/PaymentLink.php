<?php
/**
 * Modèle PaymentLink - Gestion des liens de paiement sécurisés
 * Permet aux admins de générer des liens de paiement pour les clients
 */

require_once __DIR__ . '/../core/Database.php';

class PaymentLink {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Générer un lien de paiement sécurisé pour une commande
     *
     * @param int $orderId ID de la commande
     * @param string $adminEmail Email de l'admin qui crée le lien
     * @param int $expiryDays Nombre de jours avant expiration (défaut: 30)
     * @param string $paymentType Type de paiement (full, deposit, balance)
     * @param float|null $amount Montant spécifique pour ce lien
     * @return array|false Informations du lien créé ou false en cas d'erreur
     */
    public function generateLink($orderId, $adminEmail, $expiryDays = 30, $paymentType = 'full', $amount = null) {
        // Vérifier que la commande existe
        $order = $this->getOrderById($orderId);
        if (!$order) {
            throw new Exception("Commande introuvable");
        }

        // Si c'est un lien de paiement intégral, vérifier s'il est déjà payé
        if ($paymentType === 'full' && $order['payment_status'] === 'paid') {
            throw new Exception("Cette commande a déjà été payée intégralement");
        }
        
        // Si c'est un lien d'acompte, vérifier s'il est déjà payé
        if ($paymentType === 'deposit' && ($order['deposit_payment_status'] ?? 'pending') === 'paid') {
            throw new Exception("L'acompte pour cette commande a déjà été payé");
        }

        // Si c'est un lien de solde, vérifier s'il est déjà payé
        if ($paymentType === 'balance' && ($order['balance_payment_status'] ?? 'pending') === 'paid') {
            throw new Exception("Le solde pour cette commande a déjà été payé");
        }

        // Si aucun montant n'est spécifié, le calculer en fonction du type
        if ($amount === null) {
            if ($paymentType === 'deposit') {
                $amount = $order['deposit_amount'] ?? 0;
            } elseif ($paymentType === 'balance') {
                $amount = $order['remaining_amount'] ?? 0;
            } else {
                $amount = $order['total_amount'] ?? $order['total'] ?? 0;
            }
        }

        if ($amount <= 0) {
            throw new Exception("Le montant à payer doit être supérieur à 0€");
        }

        // Générer un token unique sécurisé (UUID v4)
        $token = $this->generateSecureToken();

        // Calculer la date d'expiration
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

        // Insérer le lien de paiement
        $query = "INSERT INTO payment_links (order_id, token, expires_at, created_by_admin, status, payment_type, amount)
                  VALUES (?, ?, ?, ?, 'active', ?, ?)";

        try {
            $this->db->execute($query, [
                $orderId,
                $token,
                $expiresAt,
                $adminEmail,
                $paymentType,
                $amount
            ]);
        } catch (Exception $e) {
            error_log("PaymentLink Error: " . $e->getMessage());
            throw new Exception("Erreur lors de l'enregistrement du lien en base: " . $e->getMessage());
        }

        $linkId = $this->db->lastInsertId();

        return [
            'id' => $linkId,
            'token' => $token,
            'order_id' => $orderId,
            'expires_at' => $expiresAt,
            'status' => 'active',
            'created_by_admin' => $adminEmail,
            'payment_type' => $paymentType,
            'amount' => $amount
        ];
    }

    /**
     * Récupérer les détails d'un lien de paiement via son token
     *
     * @param string $token Token du lien
     * @return array|false Informations du lien et de la commande associée
     */
    public function getLinkByToken($token) {
        $query = "SELECT pl.*, pl.id as link_id,
                         o.id as order_id, o.order_number, o.total_amount, o.status as order_status,
                         o.shipping_address, o.billing_address, o.payment_status,
                         o.deposit_percentage, o.deposit_amount, o.remaining_amount,
                         o.created_at as order_created_at,
                         c.email, c.first_name, c.last_name, c.phone
                  FROM payment_links pl
                  JOIN orders o ON pl.order_id = o.id
                  LEFT JOIN customers c ON o.customer_id = c.id
                  WHERE pl.token = ?";

        $result = $this->db->query($query, [$token]);

        if (empty($result)) {
            return false;
        }

        return $result[0];
    }

    /**
     * Récupérer les items d'une commande via un token de paiement
     *
     * @param string $token Token du lien
     * @return array Items de la commande (configurations + échantillons)
     */
    public function getOrderItemsByToken($token) {
        $link = $this->getLinkByToken($token);
        if (!$link) {
            return false;
        }

        // Récupérer les configurations
        $configQuery = "SELECT oi.*, sc.name as config_name, sc.thumbnail_url
                        FROM order_items oi
                        LEFT JOIN saved_configurations sc ON oi.configuration_id = sc.id
                        WHERE oi.order_id = ?";

        $configs = $this->db->query($configQuery, [$link['order_id']]);

        // Récupérer les échantillons
        $sampleQuery = "SELECT * FROM order_sample_items WHERE order_id = ?";
        $samples = $this->db->query($sampleQuery, [$link['order_id']]);

        return [
            'configurations' => $configs ?: [],
            'samples' => $samples ?: [],
            'order' => $link
        ];
    }

    /**
     * Valider qu'un lien est utilisable
     *
     * @param string $token Token du lien
     * @return array ['valid' => bool, 'message' => string, 'link' => array]
     */
    public function validateLink($token) {
        $link = $this->getLinkByToken($token);

        if (!$link) {
            return [
                'valid' => false,
                'message' => 'Lien de paiement invalide ou introuvable',
                'link' => null
            ];
        }

        // Vérifier si le lien est expiré
        if (strtotime($link['expires_at']) < time()) {
            return [
                'valid' => false,
                'message' => 'Ce lien de paiement a expiré',
                'link' => $link
            ];
        }

        // Vérifier si le lien a déjà été utilisé
        if ($link['status'] === 'used') {
            return [
                'valid' => false,
                'message' => 'Ce lien de paiement a déjà été utilisé',
                'link' => $link
            ];
        }

        // Vérifier si le lien a été révoqué
        if ($link['status'] === 'revoked') {
            return [
                'valid' => false,
                'message' => 'Ce lien de paiement a été révoqué',
                'link' => $link
            ];
        }

        // Vérifier si la commande a déjà été payée
        if ($link['payment_status'] === 'paid') {
            return [
                'valid' => false,
                'message' => 'Cette commande a déjà été payée',
                'link' => $link
            ];
        }

        return [
            'valid' => true,
            'message' => 'Lien valide',
            'link' => $link
        ];
    }

    /**
     * Marquer un lien comme consulté (première visite)
     *
     * @param string $token Token du lien
     * @return bool
     */
    public function markAsAccessed($token) {
        $query = "UPDATE payment_links
                  SET accessed_at = CURRENT_TIMESTAMP
                  WHERE token = ? AND accessed_at IS NULL";

        return $this->db->execute($query, [$token]);
    }

    /**
     * Marquer un lien comme utilisé (paiement effectué)
     *
     * @param string $token Token du lien
     * @return bool
     */
    public function markAsUsed($token) {
        $query = "UPDATE payment_links
                  SET status = 'used', paid_at = CURRENT_TIMESTAMP
                  WHERE token = ?";

        return $this->db->execute($query, [$token]);
    }

    /**
     * Révoquer un lien de paiement
     *
     * @param int $linkId ID du lien
     * @return bool
     */
    public function revokeLink($linkId) {
        $query = "UPDATE payment_links
                  SET status = 'revoked'
                  WHERE id = ?";

        return $this->db->execute($query, [$linkId]);
    }

    /**
     * Récupérer tous les liens de paiement d'une commande
     *
     * @param int $orderId ID de la commande
     * @return array
     */
    public function getLinksByOrderId($orderId) {
        $query = "SELECT * FROM payment_links
                  WHERE order_id = ?
                  ORDER BY created_at DESC";

        return $this->db->query($query, [$orderId]) ?: [];
    }

    /**
     * Nettoyer les liens expirés (marquer comme expired)
     *
     * @return int Nombre de liens mis à jour
     */
    public function cleanExpiredLinks() {
        $query = "UPDATE payment_links
                  SET status = 'expired'
                  WHERE status = 'active'
                  AND expires_at < CURRENT_TIMESTAMP";

        return $this->db->execute($query, []);
    }

    /**
     * Générer un token sécurisé unique (UUID v4)
     *
     * @return string
     */
    private function generateSecureToken() {
        // Générer un UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Récupérer une commande par ID
     *
     * @param int $orderId
     * @return array|false
     */
    private function getOrderById($orderId) {
        $query = "SELECT * FROM orders WHERE id = ?";
        $result = $this->db->query($query, [$orderId]);

        return $result ? $result[0] : false;
    }

    /**
     * Récupérer les statistiques des liens de paiement
     *
     * @return array
     */
    public function getStatistics() {
        $query = "SELECT
                    COUNT(*) as total_links,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_links,
                    SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_links,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_links,
                    SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked_links,
                    SUM(CASE WHEN accessed_at IS NOT NULL THEN 1 ELSE 0 END) as accessed_links
                  FROM payment_links";

        $result = $this->db->query($query, []);
        return $result ? $result[0] : [];
    }
}
