<?php
/**
 * API Orders: Valider une commande (pour échantillons gratuits)
 * POST /api/orders/validate - Valider une commande sans paiement (échantillons gratuits)
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['order_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'order_id requis']);
            exit;
        }

        $orderId = $data['order_id'];
        $paymentMethod = $data['payment_method'] ?? 'free_samples';

        $db = Database::getInstance();

        // Vérifier que la commande appartient bien au client
        $order = $db->queryOne(
            "SELECT * FROM orders WHERE id = ? AND customer_id = ?",
            [$orderId, $_SESSION['customer_id']]
        );

        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Commande non trouvée']);
            exit;
        }

        // Vérifier que c'est bien une commande gratuite (total = 0)
        $orderTotal = $order['total_amount'] ?? $order['total'] ?? 0;
        if ($orderTotal > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cette commande nécessite un paiement']);
            exit;
        }

        // Mettre à jour le statut de la commande
        $db->execute(
            "UPDATE orders
             SET payment_status = 'paid',
                 payment_method = ?,
                 status = 'confirmed',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$paymentMethod, $orderId]
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Commande validée',
            'order_id' => $orderId
        ]);

    } catch (Exception $e) {
        error_log("[ORDERS VALIDATE] Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Erreur serveur',
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
}
