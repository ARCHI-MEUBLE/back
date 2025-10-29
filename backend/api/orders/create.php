<?php
/**
 * API: Créer une commande
 * POST /api/orders/create
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Vérifier l'authentification
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../models/AdminNotification.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (empty($data['shipping_address'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Adresse de livraison requise']);
        exit;
    }

    $order = new Order();

    $result = $order->createFromCart(
        $_SESSION['customer_id'],
        $data['shipping_address'],
        $data['billing_address'] ?? $data['shipping_address'],
        $data['payment_method'] ?? 'stripe',
        $data['notes'] ?? null
    );

    // Créer une notification pour les admins
    try {
        $notification = new AdminNotification();
        $orderNumber = $result['order_number'];
        $total = $result['total'];
        $customerName = isset($result['customer']) ?
            trim(($result['customer']['first_name'] ?? '') . ' ' . ($result['customer']['last_name'] ?? '')) :
            'Client';

        $message = "Nouvelle commande #{$orderNumber} de {$customerName} pour {$total}€";
        $notification->create('order', $message, $result['id']);
    } catch (Exception $e) {
        // Ne pas bloquer la création de commande si la notification échoue
        error_log("Erreur création notification: " . $e->getMessage());
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Commande créée avec succès',
        'order' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
