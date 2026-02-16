<?php
/**
 * API Admin: Définir la stratégie de paiement d'une commande
 * POST /api/admin/orders/payment-strategy.php
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../models/Order.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification admin
$session = Session::getInstance();
if (!$session->has('admin_email')) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['order_id']) || empty($data['strategy'])) {
        http_response_code(400);
        echo json_encode(['error' => 'order_id et strategy requis']);
        exit;
    }

    $orderModel = new Order();
    $ok = $orderModel->updatePaymentStrategy(
        (int)$data['order_id'], 
        $data['strategy'], 
        $data['deposit_percentage'] ?? 0
    );

    if ($ok) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Stratégie de paiement mise à jour']);
    } else {
        throw new Exception('Échec de la mise à jour');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
