<?php
/**
 * API: Lister mes commandes
 * GET /api/orders/list
 * GET /api/orders/list?id=123 - Détails d'une commande
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    $order = new Order();
    $customerId = $_SESSION['customer_id'];
    
    if (isset($_GET['id'])) {
        // Récupérer une commande spécifique
        $orderData = $order->getById($_GET['id']);
        
        if (!$orderData || $orderData['customer_id'] != $customerId) {
            http_response_code(404);
            echo json_encode(['error' => 'Commande non trouvée']);
            exit;
        }
        
        // Récupérer les items de la commande
        $items = $order->getItems($_GET['id']);
        $orderData['items'] = $items;
        
        http_response_code(200);
        echo json_encode(['order' => $orderData]);
        
    } else {
        // Lister toutes les commandes
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $orders = $order->getByCustomer($customerId, $limit, $offset);
        
        http_response_code(200);
        echo json_encode(['orders' => $orders]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
