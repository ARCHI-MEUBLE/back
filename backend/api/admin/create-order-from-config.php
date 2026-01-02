<?php
/**
 * API Admin - Créer une commande depuis une configuration
 * POST /api/admin/create-order-from-config
 *
 * Après validation téléphonique, l'admin transforme une config en commande
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    // Vérifier l'authentification admin
    if (!isset($_SESSION['admin_email'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Non authentifié'
        ]);
        exit;
    }

    // Vérifier la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Méthode non autorisée'
        ]);
        exit;
    }

    // Récupérer les données de la requête
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['configuration_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de configuration manquant'
        ]);
        exit;
    }

    $configId = intval($data['configuration_id']);
    $db = Database::getInstance();

    // Récupérer la configuration avec les infos client
    $query = "SELECT c.*,
                     cust.id as customer_id,
                     cust.email as customer_email,
                     cust.first_name,
                     cust.last_name,
                     cust.phone,
                     cust.address,
                     cust.city,
                     cust.postal_code,
                     cust.country
              FROM configurations c
              LEFT JOIN customers cust ON CAST(c.user_id AS INTEGER) = cust.id
              WHERE c.id = ?";

    $config = $db->queryOne($query, [$configId]);

    if (!$config) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Configuration introuvable'
        ]);
        exit;
    }

    // Vérifier que la config n'a pas déjà été transformée en commande
    if ($config['status'] === 'en_commande' || $config['status'] === 'payee') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Cette configuration a déjà été transformée en commande'
        ]);
        exit;
    }

    // Vérifier qu'il y a un client associé
    if (!$config['customer_id']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Aucun client associé à cette configuration'
        ]);
        exit;
    }

    // Construire l'adresse de livraison
    $shippingAddress = trim(
        ($config['address'] ?? '') . ', ' .
        ($config['city'] ?? '') . ', ' .
        ($config['postal_code'] ?? '') . ', ' .
        ($config['country'] ?? 'France')
    );

    // Générer un numéro de commande unique
    $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

    // Créer la commande
    $insertOrderQuery = "INSERT INTO orders (
        customer_id,
        order_number,
        total_amount,
        shipping_address,
        billing_address,
        payment_method,
        payment_status,
        status,
        notes,
        admin_notes,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

    $adminNotes = "Commande créée depuis configuration #{$configId} par " . $_SESSION['admin_email'];

    $db->execute($insertOrderQuery, [
        $config['customer_id'],
        $orderNumber,
        $config['price'],
        $shippingAddress,
        $shippingAddress, // Utiliser la même adresse pour facturation
        'card',
        'pending',
        'pending',
        'Commande créée par l\'admin après validation téléphonique',
        $adminNotes
    ]);

    $orderId = $db->lastInsertId();

    // Créer l'item de commande depuis la configuration
    $insertItemQuery = "INSERT INTO order_items (
        order_id,
        configuration_id,
        prompt,
        config_data,
        glb_url,
        quantity,
        unit_price,
        total_price,
        production_status,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

    $db->execute($insertItemQuery, [
        $orderId,
        $configId,
        $config['prompt'],
        $config['config_string'],
        $config['glb_url'],
        1, // Quantité par défaut
        $config['price'],
        $config['price'],
        'pending'
    ]);

    // Mettre à jour le statut de la configuration
    $updateConfigQuery = "UPDATE configurations
                          SET status = 'en_commande'
                          WHERE id = ?";
    $db->execute($updateConfigQuery, [$configId]);

    // Créer une notification admin
    require_once __DIR__ . '/../../models/AdminNotification.php';
    $notification = new AdminNotification();
    $notification->create(
        'new_order',
        "Nouvelle commande #{$orderNumber} créée depuis config",
        $orderId
    );

    // --- GÉNÉRATION DU LIEN DE PAIEMENT ET ENVOI EMAIL ---
    $paymentLinkUrl = null;
    try {
        require_once __DIR__ . '/../../models/PaymentLink.php';
        require_once __DIR__ . '/../../services/EmailService.php';
        
        $paymentLinkModel = new PaymentLink();
        $emailService = new EmailService();
        
        // Générer le lien (valable 30 jours par défaut)
        $paymentLink = $paymentLinkModel->generateLink($orderId, $_SESSION['admin_email']);
        
        if ($paymentLink) {
            // Construire l'URL du lien
            $baseUrl = getenv('FRONTEND_URL') ?: 'http://localhost:3000';
            $paymentLinkUrl = $baseUrl . '/paiement/' . $paymentLink['token'];
            
            // Envoyer l'email au client
            $customerName = trim(($config['first_name'] ?? '') . ' ' . ($config['last_name'] ?? ''));
            $emailService->sendPaymentLinkEmail(
                $config['customer_email'],
                $customerName ?: 'Client',
                $orderNumber,
                $paymentLinkUrl,
                $paymentLink['expires_at'],
                $config['price']
            );
        }
    } catch (Exception $paymentError) {
        // On logge l'erreur mais on ne bloque pas la réponse (la commande est créée)
        error_log("Erreur lors de la génération du lien de paiement: " . $paymentError->getMessage());
    }

    // Retourner la commande créée
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'total_amount' => $config['price'],
            'customer_email' => $config['customer_email'],
            'customer_name' => trim($config['first_name'] . ' ' . $config['last_name']),
            'payment_link' => $paymentLinkUrl
        ],
        'message' => 'Commande créée avec succès et lien de paiement envoyé'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
