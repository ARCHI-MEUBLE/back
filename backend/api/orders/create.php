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
