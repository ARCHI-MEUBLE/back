<?php
/**
 * ArchiMeuble - Modèle Cart
 * Gère le panier en session
 * Auteur : Claude Code
 * Date : 2025-10-28
 */

require_once __DIR__ . '/../core/Database.php';

class Cart {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère les items du panier avec les détails des configurations
     * @param int $customerId
     * @return array
     */
    public function getItems($customerId) {
        $query = "
            SELECT
                ci.id,
                ci.configuration_id,
                ci.quantity,
                ci.added_at,
                c.id as config_id,
                c.prompt,
                c.price,
                c.glb_url,
                c.config_string,
                c.created_at as config_created_at
            FROM cart_items ci
            JOIN configurations c ON ci.configuration_id = c.id
            WHERE ci.customer_id = :customer_id
            ORDER BY ci.added_at DESC
        ";

        $cartItems = $this->db->query($query, ['customer_id' => $customerId]);

        $items = [];
        foreach ($cartItems as $item) {
            // Décoder le config_string pour obtenir le nom et autres détails
            $configData = json_decode($item['config_string'], true) ?? [];

            $items[] = [
                'id' => $item['id'],
                'configuration_id' => $item['configuration_id'],
                'quantity' => $item['quantity'],
                'added_at' => $item['added_at'],
                'configuration' => [
                    'id' => $item['config_id'],
                    'name' => $configData['name'] ?? 'Configuration sans nom',
                    'prompt' => $item['prompt'],
                    'price' => $item['price'],
                    'glb_url' => $item['glb_url'],
                    'thumbnail_url' => $configData['thumbnail_url'] ?? null,
                    'config_data' => $configData,
                    'created_at' => $item['config_created_at']
                ]
            ];
        }

        return $items;
    }

    /**
     * Ajoute un item au panier
     * @param int $customerId
     * @param int $configurationId
     * @param int $quantity
     * @return bool
     * @throws Exception Si la configuration n'est pas validée
     */
    public function addItem($customerId, $configurationId, $quantity = 1) {
        // Vérifier le statut de la configuration
        $checkQuery = "SELECT status FROM configurations WHERE id = :id";
        $config = $this->db->queryOne($checkQuery, ['id' => $configurationId]);
        
        if (!$config) {
            throw new Exception("Configuration introuvable");
        }
        
        if ($config['status'] !== 'validee') {
            throw new Exception("Cette configuration doit être validée par un menuisier avant d'être ajoutée au panier.");
        }

        // Vérifier si l'item existe déjà
        $query = "SELECT id, quantity FROM cart_items WHERE customer_id = :customer_id AND configuration_id = :configuration_id";
        $existing = $this->db->queryOne($query, [
            'customer_id' => $customerId,
            'configuration_id' => $configurationId
        ]);

        if ($existing) {
            // Augmenter la quantité
            $newQuantity = $existing['quantity'] + $quantity;
            $updateQuery = "UPDATE cart_items SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $this->db->execute($updateQuery, [
                'quantity' => $newQuantity,
                'id' => $existing['id']
            ]);
        } else {
            // Ajouter nouveau
            $insertQuery = "INSERT INTO cart_items (customer_id, configuration_id, quantity) VALUES (:customer_id, :configuration_id, :quantity)";
            $this->db->execute($insertQuery, [
                'customer_id' => $customerId,
                'configuration_id' => $configurationId,
                'quantity' => $quantity
            ]);
        }

        return true;
    }

    /**
     * Met à jour la quantité d'un item
     * @param int $customerId
     * @param int $configurationId
     * @param int $quantity
     * @return bool
     */
    public function updateQuantity($customerId, $configurationId, $quantity) {
        if ($quantity <= 0) {
            // Si quantité <= 0, supprimer l'item
            return $this->removeItem($customerId, $configurationId);
        }

        $query = "UPDATE cart_items SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP
                  WHERE customer_id = :customer_id AND configuration_id = :configuration_id";

        $this->db->execute($query, [
            'quantity' => $quantity,
            'customer_id' => $customerId,
            'configuration_id' => $configurationId
        ]);

        return true;
    }

    /**
     * Retire un item du panier
     * @param int $customerId
     * @param int $configurationId
     * @return bool
     */
    public function removeItem($customerId, $configurationId) {
        $query = "DELETE FROM cart_items WHERE customer_id = :customer_id AND configuration_id = :configuration_id";

        $this->db->execute($query, [
            'customer_id' => $customerId,
            'configuration_id' => $configurationId
        ]);

        return true;
    }

    /**
     * Compte les items dans le panier
     * @param int $customerId
     * @return int
     */
    public function countItems($customerId) {
        $query = "SELECT SUM(quantity) as total FROM cart_items WHERE customer_id = :customer_id";
        $result = $this->db->queryOne($query, ['customer_id' => $customerId]);

        return (int)($result['total'] ?? 0);
    }

    /**
     * Calcule le total du panier
     * @param int $customerId
     * @return float
     */
    public function getTotal($customerId) {
        $items = $this->getItems($customerId);
        $total = 0.0;

        foreach ($items as $item) {
            $price = $item['configuration']['price'] ?? 0.0;
            $quantity = $item['quantity'] ?? 1;
            $total += $price * $quantity;
        }

        return $total;
    }

    /**
     * Vide le panier
     * @param int $customerId
     * @return bool
     */
    public function clear($customerId) {
        $query = "DELETE FROM cart_items WHERE customer_id = :customer_id";

        $this->db->execute($query, ['customer_id' => $customerId]);

        return true;
    }
}
