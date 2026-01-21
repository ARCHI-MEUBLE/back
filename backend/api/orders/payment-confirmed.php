<?php
/**
 * API: Confirmer le paiement d'une commande
 * POST /api/orders/{id}/payment-confirmed
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
require_once __DIR__ . '/../../models/Order.php';

try {
    // Récupérer l'ID de la commande depuis l'URL
    $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de commande manquant']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['payment_intent_id']) || !isset($data['payment_status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données manquantes']);
        exit;
    }

    $db = Database::getInstance();

    // Vérifier que la commande appartient au client
    $query = "SELECT id, customer_id FROM orders WHERE id = ?";
    $order = $db->queryOne($query, [$orderId]);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Commande non trouvée']);
        exit;
    }

    if ((int)$order['customer_id'] !== (int)$_SESSION['customer_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès interdit']);
        exit;
    }

    // Mettre à jour la commande avec les infos de paiement
    $updateQuery = "UPDATE orders
                    SET stripe_payment_intent_id = ?,
                        payment_status = ?,
                        status = 'confirmed',
                        confirmed_at = CURRENT_TIMESTAMP,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";

    $db->execute($updateQuery, [
        $data['payment_intent_id'],
        $data['payment_status'],
        $orderId
    ]);

    // Créer une notification pour l'admin
    require_once __DIR__ . '/../../models/AdminNotification.php';
    $notification = new AdminNotification();
    $notification->create(
        'payment',
        "Paiement confirmé pour la commande #{$orderId}",
        $orderId
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Paiement confirmé'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
