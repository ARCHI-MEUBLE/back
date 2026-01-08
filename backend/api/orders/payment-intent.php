<?php
/**
 * API: Associer un payment intent à une commande
 * POST /api/orders/{id}/payment-intent
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

require_once __DIR__ . '/../../core/Database.php';

try {
    $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de commande manquant']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['payment_intent_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment intent ID manquant']);
        exit;
    }

    $paymentType = $data['payment_type'] ?? 'full';

    $db = Database::getInstance();

    // Vérifier que la commande appartient au client
    $query = "SELECT id, customer_id FROM orders WHERE id = ?";
    $order = $db->queryOne($query, [$orderId]);

    if (!$order || $order['customer_id'] != $_SESSION['customer_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès interdit']);
        exit;
    }

    // Mettre à jour avec le payment intent ID
    if ($paymentType === 'deposit') {
        $updateQuery = "UPDATE orders
                        SET deposit_stripe_intent_id = ?,
                            deposit_payment_status = 'pending',
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";
    } elseif ($paymentType === 'balance') {
        $updateQuery = "UPDATE orders
                        SET balance_stripe_intent_id = ?,
                            balance_payment_status = 'pending',
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";
    } else {
        $updateQuery = "UPDATE orders
                        SET stripe_payment_intent_id = ?,
                            payment_status = 'pending',
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";
    }

    $db->execute($updateQuery, [$data['payment_intent_id'], $orderId]);

    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
