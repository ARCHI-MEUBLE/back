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
        // Récupérer les items du panier (configurations)
        require_once __DIR__ . '/Cart.php';
        $cart = new Cart();
        $cartItems = $cart->getItems($customerId);

        // Récupérer les échantillons du panier
        $samplesQuery = "
            SELECT csi.id, csi.sample_color_id, csi.quantity,
                   sc.name as color_name, sc.hex, sc.image_url,
                   st.name as type_name, st.material
            FROM cart_sample_items csi
            JOIN sample_colors sc ON csi.sample_color_id = sc.id
            JOIN sample_types st ON sc.type_id = st.id
            WHERE csi.customer_id = ?
        ";
        $sampleItems = $this->db->query($samplesQuery, [$customerId]);

        // Vérifier qu'il y a au moins des configs OU des échantillons
        if (empty($cartItems) && empty($sampleItems)) {
            throw new Exception('Panier vide');
        }

        // Calculer le total (uniquement les configs, échantillons = 0€)
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

        // Insérer les items de commande (configurations)
        foreach ($cartItems as $item) {
            $configData = json_encode($item['configuration']['config_data']);
            $unitPrice = $item['configuration']['price'];
            $quantity = $item['quantity'];
            $totalPrice = $unitPrice * $quantity;

            $insertItemQuery = "INSERT INTO order_items (order_id, configuration_id, prompt, config_data, glb_url, quantity, unit_price, total_price)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $this->db->execute($insertItemQuery, [
                $orderId,
                $item['configuration_id'],
                $item['configuration']['prompt'],
                $configData,
                $item['configuration']['glb_url'],
                $quantity,
                $unitPrice,
                $totalPrice
            ]);
        }

        // Insérer les échantillons de commande
        foreach ($sampleItems as $sample) {
            $insertSampleQuery = "INSERT INTO order_sample_items
                (order_id, sample_color_id, sample_name, sample_type_name, material, image_url, hex, quantity, price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->db->execute($insertSampleQuery, [
                $orderId,
                $sample['sample_color_id'],
                $sample['color_name'],
                $sample['type_name'],
                $sample['material'],
                $sample['image_url'],
                $sample['hex'],
                $sample['quantity'],
                0.00 // Prix = 0€ pour échantillons gratuits
            ]);
        }

        // Vider le panier (configurations ET échantillons)
        $cart->clear($customerId);
        $this->db->execute("DELETE FROM cart_sample_items WHERE customer_id = ?", [$customerId]);

        // Récupérer les infos du client
        require_once __DIR__ . '/Customer.php';
        $customerModel = new Customer();
        $customerData = $customerModel->getById($customerId);

        return [
            'id' => $orderId,
            'order_number' => $orderNumber,
            'total' => $total,
            'samples_count' => count($sampleItems),
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
     * Récupérer les échantillons d'une commande
     */
    public function getOrderSamples($orderId) {
        $query = "SELECT * FROM order_sample_items WHERE order_id = ? ORDER BY id";
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
     * Supprimer une commande et ses items
     */
    public function delete($orderId) {
        try {
            // Supprimer les items de commande d'abord (contraintes de clé étrangère)
            $deleteItemsQuery = "DELETE FROM order_items WHERE order_id = ?";
            $this->db->execute($deleteItemsQuery, [$orderId]);

            // Supprimer la commande
            $deleteOrderQuery = "DELETE FROM orders WHERE id = ?";
            return $this->db->execute($deleteOrderQuery, [$orderId]);
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression de commande {$orderId}: " . $e->getMessage());
            return false;
        }
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
