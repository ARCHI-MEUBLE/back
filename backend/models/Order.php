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
                   sc.unit_price, sc.price_per_m2,
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

        // Calculer le total
        $total = 0;
        foreach ($cartItems as $item) {
            $total += $item['configuration']['price'] * $item['quantity'];
        }
        foreach ($sampleItems as $sample) {
            $total += ($sample['unit_price'] ?? 0) * $sample['quantity'];
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

            // Mettre à jour le statut de la configuration
            $this->db->execute("UPDATE configurations SET status = 'en_commande' WHERE id = ?", [$item['configuration_id']]);
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
                $sample['unit_price'] ?? 0.00
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
     * Mettre à jour la stratégie de paiement pour une commande
     */
    public function updatePaymentStrategy($orderId, $strategy, $depositPercentage = 0) {
        $allowedStrategies = ['full', 'deposit'];
        if (!in_array($strategy, $allowedStrategies)) {
            throw new Exception('Stratégie de paiement invalide');
        }

        $order = $this->getById($orderId);
        if (!$order) {
            throw new Exception('Commande introuvable');
        }

        // Bloquer si un paiement a déjà été effectué
        if (($order['deposit_payment_status'] ?? '') === 'paid' || ($order['payment_status'] ?? '') === 'paid') {
            throw new Exception('Impossible de modifier la stratégie après un paiement');
        }

        // Calculer les montants si c'est un acompte
        $depositAmount = 0;
        $remainingAmount = $order['total_amount'] ?? $order['total'] ?? 0;

        if ($strategy === 'deposit') {
            $depositPercentage = (float)$depositPercentage;
            if ($depositPercentage <= 0 || $depositPercentage >= 100) {
                throw new Exception('Le pourcentage d\'acompte doit être entre 1 et 99');
            }
            
            // On ne calcule l'acompte que sur la partie meuble ? 
            // L'utilisateur a dit "uniquement pour les configurations 3D et non pour les échantillons"
            // Récupérer le total des meubles
            $items = $this->getOrderItems($orderId);
            $furnitureTotal = 0;
            foreach ($items as $item) {
                $furnitureTotal += $item['total_price'];
            }

            // Récupérer le total des échantillons
            $samples = $this->getOrderSamples($orderId);
            $samplesTotal = 0;
            foreach ($samples as $sample) {
                $samplesTotal += ($sample['price'] * $sample['quantity']);
            }

            $depositAmount = ($furnitureTotal * ($depositPercentage / 100)) + $samplesTotal; // Les échantillons sont payés direct ? Ou pas du tout ? 
            // "non pour les échantillons" peut vouloir dire qu'on ne fait pas d'acompte sur eux, 
            // donc on les fait payer 100% dès le premier paiement.
            
            $remainingAmount = ($order['total_amount'] ?? $order['total'] ?? 0) - $depositAmount;
        }

        $query = "UPDATE orders SET 
                    payment_strategy = ?, 
                    deposit_percentage = ?, 
                    deposit_amount = ?, 
                    remaining_amount = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                  WHERE id = ?";
        
        return $this->db->execute($query, [
            $strategy, 
            $depositPercentage, 
            $depositAmount, 
            $remainingAmount, 
            $orderId
        ]);
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateStatus($orderId, $status, $adminNotes = null) {
        $allowedStatuses = ['pending', 'confirmed', 'in_production', 'shipped', 'delivered', 'cancelled'];

        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('Statut invalide');
        }

        // Si la commande est annulée, révoquer les liens de paiement actifs
        if ($status === 'cancelled') {
            $this->db->execute("UPDATE payment_links SET status = 'revoked' WHERE order_id = ? AND status = 'active'", [$orderId]);
            
            // Et libérer les configurations
            $items = $this->getOrderItems($orderId);
            foreach ($items as $item) {
                if (isset($item['configuration_id'])) {
                    // Si on annule, la config redevient 'termine' pour pouvoir être commandée à nouveau
                    $this->db->execute("UPDATE configurations SET status = 'termine' WHERE id = ?", [$item['configuration_id']]);
                }
            }

            // Envoyer un email d'annulation au client
            try {
                require_once __DIR__ . '/../services/EmailService.php';
                $emailService = new EmailService();
                $orderData = $this->getById($orderId);
                
                // Récupérer les infos du client
                $customer = $this->db->queryOne("SELECT email, first_name FROM customers WHERE id = ?", [$orderData['customer_id']]);
                
                if ($customer) {
                    $emailService->sendOrderCancelledEmail($customer['email'], $customer['first_name'], $orderData['order_number']);
                }
            } catch (Exception $e) {
                error_log("Erreur lors de l'envoi de l'email d'annulation: " . $e->getMessage());
            }
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
     * Supprimer une commande et ses items, libérer les configurations et révoquer les liens
     */
    public function delete($orderId) {
        try {
            // 1. Récupérer les items de la commande pour libérer les configurations
            $items = $this->getOrderItems($orderId);
            foreach ($items as $item) {
                if (isset($item['configuration_id'])) {
                    // Remettre la configuration à un statut 'pret' ou 'termine' 
                    // (le statut 'en_commande' l'empêchait d'être commandée à nouveau)
                    $this->db->execute("UPDATE configurations SET status = 'termine' WHERE id = ?", [$item['configuration_id']]);
                }
            }

            // 2. Supprimer les items de commande de meubles
            $deleteItemsQuery = "DELETE FROM order_items WHERE order_id = ?";
            $this->db->execute($deleteItemsQuery, [$orderId]);

            // 3. Supprimer les items d'échantillons
            $deleteSamplesQuery = "DELETE FROM order_sample_items WHERE order_id = ?";
            $this->db->execute($deleteSamplesQuery, [$orderId]);

            // 4. Révoquer tous les liens de paiement actifs pour cette commande
            $revokeLinksQuery = "UPDATE payment_links SET status = 'revoked' WHERE order_id = ? AND status = 'active'";
            $this->db->execute($revokeLinksQuery, [$orderId]);

            // 5. Supprimer la commande elle-même
            $deleteOrderQuery = "DELETE FROM orders WHERE id = ?";
            return $this->db->execute($deleteOrderQuery, [$orderId]);
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression complète de commande {$orderId}: " . $e->getMessage());
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
            'payment_strategy' => $orderData['payment_strategy'] ?? 'full',
            'deposit_percentage' => $orderData['deposit_percentage'] ?? 0,
            'deposit_amount' => $orderData['deposit_amount'] ?? 0,
            'remaining_amount' => $orderData['remaining_amount'] ?? 0,
            'deposit_payment_status' => $orderData['deposit_payment_status'] ?? 'pending',
            'balance_payment_status' => $orderData['balance_payment_status'] ?? 'pending',
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
