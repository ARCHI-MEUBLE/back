<?php
/**
 * Modèle Order - Gestion des commandes
 */

require_once __DIR__ . '/../core/Database.php';

class Order {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Créer une commande à partir du panier
     */
    public function createFromCart($customerId, $shippingAddress, $billingAddress, $paymentMethod = 'card', $notes = null) {
        // Récupérer les items du panier
        require_once __DIR__ . '/Cart.php';
        $cart = new Cart();
        $cartItems = $cart->getItems($customerId);

        if (empty($cartItems)) {
            throw new Exception('Panier vide');
        }

        // Calculer le total
        $total = 0;
        foreach ($cartItems as $item) {
            $total += $item['configuration']['price'] * $item['quantity'];
        }

        // Générer un numéro de commande unique
        $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

        // Insérer la commande
        $query = "INSERT INTO orders (customer_id, order_number, total_amount, shipping_address, billing_address, payment_method, notes)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";

        $this->db->execute($query, [
            $customerId,
            $orderNumber,
            $total,
            $shippingAddress,
            $billingAddress,
            $paymentMethod,
            $notes
        ]);

        $orderId = $this->db->lastInsertId();

        // Insérer les items de commande
        foreach ($cartItems as $item) {
            $configData = json_encode($item['configuration']['config_data']);
            $insertItemQuery = "INSERT INTO order_items (order_id, configuration_id, prompt, config_data, glb_url, quantity, unit_price)
                                VALUES (?, ?, ?, ?, ?, ?, ?)";

            $this->db->execute($insertItemQuery, [
                $orderId,
                $item['configuration_id'],
                $item['configuration']['prompt'],
                $configData,
                $item['configuration']['glb_url'],
                $item['quantity'],
                $item['configuration']['price']
            ]);
        }

        // Vider le panier
        $cart->clear($customerId);

        // Récupérer les infos du client
        require_once __DIR__ . '/Customer.php';
        $customerModel = new Customer();
        $customerData = $customerModel->getById($customerId);

        return [
            'id' => $orderId,
            'order_number' => $orderNumber,
            'total' => $total,
            'customer' => $customerData
        ];
    }

    /**
     * Récupérer une commande par ID
     */
    public function getById($id) {
        $query = "SELECT * FROM orders WHERE id = ?";
        return $this->db->queryOne($query, [$id]);
    }

    /**
     * Récupérer toutes les commandes d'un client
     */
    public function getByCustomerId($customerId) {
        $query = "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC";
        return $this->db->query($query, [$customerId]);
    }

    /**
     * Récupérer les items d'une commande
     */
    public function getOrderItems($orderId) {
        $query = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
        return $this->db->query($query, [$orderId]);
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateStatus($orderId, $status, $adminNotes = null) {
        $allowedStatuses = ['pending', 'confirmed', 'in_production', 'shipped', 'delivered', 'cancelled'];

        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('Statut invalide');
        }

        if ($adminNotes) {
            $query = "UPDATE orders SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            return $this->db->execute($query, [$status, $adminNotes, $orderId]);
        } else {
            $query = "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            return $this->db->execute($query, [$status, $orderId]);
        }
    }

    /**
     * Compter les commandes par statut
     */
    public function countByStatus($status = null) {
        if ($status) {
            $query = "SELECT COUNT(*) as count FROM orders WHERE status = ?";
            $result = $this->db->queryOne($query, [$status]);
        } else {
            $query = "SELECT COUNT(*) as count FROM orders";
            $result = $this->db->queryOne($query);
        }
        return $result ? $result['count'] : 0;
    }

    /**
     * Récupérer toutes les commandes (pour admin)
     */
    public function getAll($status = null, $limit = 100, $offset = 0) {
        if ($status) {
            $query = "SELECT o.*, c.email as customer_email, c.first_name, c.last_name
                      FROM orders o
                      JOIN customers c ON o.customer_id = c.id
                      WHERE o.status = ?
                      ORDER BY o.created_at DESC
                      LIMIT ? OFFSET ?";
            return $this->db->query($query, [$status, $limit, $offset]);
        } else {
            $query = "SELECT o.*, c.email as customer_email, c.first_name, c.last_name
                      FROM orders o
                      JOIN customers c ON o.customer_id = c.id
                      ORDER BY o.created_at DESC
                      LIMIT ? OFFSET ?";
            return $this->db->query($query, [$limit, $offset]);
        }
    }

    /**
     * Compter le nombre total de commandes
     */
    public function count() {
        $query = "SELECT COUNT(*) as count FROM orders";
        $result = $this->db->queryOne($query);
        return $result ? $result['count'] : 0;
    }

    /**
     * Alias pour getOrderItems (compatibilité)
     */
    public function getItems($orderId) {
        return $this->getOrderItems($orderId);
    }

    /**
     * Alias pour getByCustomerId (compatibilité)
     */
    public function getByCustomer($customerId, $limit = 50, $offset = 0) {
        $query = "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return $this->db->query($query, [$customerId, $limit, $offset]);
    }

    /**
     * Formater une commande pour le frontend
     */
    public function formatForFrontend($orderData) {
        // Mapper les champs de la base de données vers le format frontend
        $formatted = [
            'id' => $orderData['id'],
            'order_number' => $orderData['order_number'],
            'status' => $orderData['status'],
            'total' => $orderData['total_amount'] ?? $orderData['total'] ?? 0,
            'amount' => $orderData['total_amount'] ?? $orderData['total'] ?? 0,
            'shipping_address' => $orderData['shipping_address'] ?? '',
            'billing_address' => $orderData['billing_address'] ?? '',
            'payment_method' => $orderData['payment_method'] ?? 'card',
            'payment_status' => $orderData['payment_status'] ?? 'pending',
            'notes' => $orderData['notes'] ?? '',
            'admin_notes' => $orderData['admin_notes'] ?? '',
            'created_at' => $orderData['created_at'],
            'updated_at' => $orderData['updated_at'] ?? $orderData['created_at'],
        ];

        // Ajouter les infos du client si disponibles
        if (isset($orderData['customer'])) {
            $formatted['customer'] = $orderData['customer'];
            $formatted['customer_name'] = ($orderData['customer']['first_name'] ?? '') . ' ' . ($orderData['customer']['last_name'] ?? '');
            $formatted['customer_email'] = $orderData['customer']['email'] ?? '';
            $formatted['customer_phone'] = $orderData['customer']['phone'] ?? '';
        } elseif (isset($orderData['customer_email'])) {
            // Infos aplaties depuis la jointure SQL
            $formatted['customer_name'] = ($orderData['first_name'] ?? '') . ' ' . ($orderData['last_name'] ?? '');
            $formatted['customer_email'] = $orderData['customer_email'];
        }

        // Décoder les config_data JSON dans les items si présents
        if (isset($orderData['items'])) {
            foreach ($orderData['items'] as &$item) {
                if (isset($item['config_data']) && is_string($item['config_data'])) {
                    $configData = json_decode($item['config_data'], true);
                    $item['name'] = $configData['name'] ?? 'Configuration sans nom';
                    $item['config_data'] = $configData;
                }
                // Mapper les champs price/unit_price
                if (!isset($item['price']) && isset($item['unit_price'])) {
                    $item['price'] = $item['unit_price'];
                }
            }
            $formatted['items'] = $orderData['items'];
        }

        return $formatted;
    }
}
