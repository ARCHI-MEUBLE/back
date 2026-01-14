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

        // Récupérer les articles du catalogue du panier
        $catalogueQuery = "
            SELECT cci.id, cci.catalogue_item_id, cci.variation_id, cci.quantity,
                   ci.name, ci.unit_price,
                   civ.color_name as variation_name,
                   COALESCE(civ.image_url, ci.image_url) as image_url
            FROM cart_catalogue_items cci
            JOIN catalogue_items ci ON cci.catalogue_item_id = ci.id
            LEFT JOIN catalogue_item_variations civ ON cci.variation_id = civ.id
            WHERE cci.customer_id = ?
        ";
        $catalogueItems = $this->db->query($catalogueQuery, [$customerId]);

        // Récupérer les façades du panier
        $facadeQuery = "SELECT * FROM facade_cart_items WHERE customer_id = ?";
        $facadeItems = $this->db->query($facadeQuery, [$customerId]);

        // Vérifier qu'il y a au moins des configs OU des échantillons OU du catalogue OU des façades
        if (empty($cartItems) && empty($sampleItems) && empty($catalogueItems) && empty($facadeItems)) {
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
        foreach ($catalogueItems as $catItem) {
            $total += $catItem['unit_price'] * $catItem['quantity'];
        }
        foreach ($facadeItems as $facade) {
            $total += $facade['unit_price'] * $facade['quantity'];
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

        // Insérer les articles du catalogue de commande
        foreach ($catalogueItems as $catItem) {
            $insertCatQuery = "INSERT INTO order_catalogue_items
                (order_id, catalogue_item_id, variation_id, product_name, variation_name, image_url, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->db->execute($insertCatQuery, [
                $orderId,
                $catItem['catalogue_item_id'],
                $catItem['variation_id'],
                $catItem['name'],
                $catItem['variation_name'],
                $catItem['image_url'],
                $catItem['quantity'],
                $catItem['unit_price'],
                $catItem['unit_price'] * $catItem['quantity']
            ]);
        }

        // Insérer les façades de commande
        foreach ($facadeItems as $facade) {
            $insertFacadeQuery = "INSERT INTO order_facade_items
                (order_id, config_data, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?)";

            $this->db->execute($insertFacadeQuery, [
                $orderId,
                $facade['config_data'],
                $facade['quantity'],
                $facade['unit_price'],
                $facade['unit_price'] * $facade['quantity']
            ]);
        }

        // Vider le panier (configurations ET échantillons ET catalogue ET façades)
        $cart->clear($customerId);
        $this->db->execute("DELETE FROM cart_sample_items WHERE customer_id = ?", [$customerId]);
        $this->db->execute("DELETE FROM cart_catalogue_items WHERE customer_id = ?", [$customerId]);
        $this->db->execute("DELETE FROM facade_cart_items WHERE customer_id = ?", [$customerId]);

        // Récupérer les infos du client
        require_once __DIR__ . '/Customer.php';
        $customerModel = new Customer();
        $customerData = $customerModel->getById($customerId);

        return [
            'id' => $orderId,
            'order_number' => $orderNumber,
            'total' => $total,
            'samples_count' => count($sampleItems),
            'facades_count' => count($facadeItems),
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
     * Récupérer les articles du catalogue d'une commande
     */
    public function getOrderCatalogueItems($orderId) {
        $query = "SELECT * FROM order_catalogue_items WHERE order_id = ? ORDER BY id";
        return $this->db->query($query, [$orderId]);
    }

    /**
     * Récupérer les façades d'une commande
     */
    public function getOrderFacadeItems($orderId) {
        $query = "SELECT * FROM order_facade_items WHERE order_id = ? ORDER BY id";
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
                $samplesTotal += (($sample['price'] ?? 0) * $sample['quantity']);
            }

            // Récupérer le total des articles du catalogue (payés à 100% dans l'acompte)
            $catalogueItems = $this->getOrderCatalogueItems($orderId);
            $catalogueTotal = 0;
            foreach ($catalogueItems as $catItem) {
                $catalogueTotal += (($catItem['total_price'] ?? ($catItem['unit_price'] * $catItem['quantity'])) ?? 0);
            }

            // Récupérer le total des façades (comme les meubles, acompte en %)
            $facadeItems = $this->getOrderFacadeItems($orderId);
            $facadeTotal = 0;
            foreach ($facadeItems as $facade) {
                $facadeTotal += (($facade['total_price'] ?? ($facade['unit_price'] * $facade['quantity'])) ?? 0);
            }

            $depositAmount = (($furnitureTotal + $facadeTotal) * ($depositPercentage / 100)) + $samplesTotal + $catalogueTotal;
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

            // 4. Supprimer les items de catalogue
            $deleteCatalogueQuery = "DELETE FROM order_catalogue_items WHERE order_id = ?";
            $this->db->execute($deleteCatalogueQuery, [$orderId]);

            // 4b. Supprimer les façades
            $deleteFacadesQuery = "DELETE FROM order_facade_items WHERE order_id = ?";
            $this->db->execute($deleteFacadesQuery, [$orderId]);

            // 5. Révoquer tous les liens de paiement actifs pour cette commande
            $revokeLinksQuery = "UPDATE payment_links SET status = 'revoked' WHERE order_id = ? AND status = 'active'";
            $this->db->execute($revokeLinksQuery, [$orderId]);

            // 6. Supprimer la commande elle-même
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

        // Ajouter les articles du catalogue si présents
        if (isset($orderData['catalogue_items'])) {
            $formatted['catalogue_items'] = $orderData['catalogue_items'];
        }

        return $formatted;
    }
}
