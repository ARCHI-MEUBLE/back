<?php
/**
 * API: Gestion du panier
 * GET /api/cart - Lister le panier
 * POST /api/cart - Ajouter au panier
 * PUT /api/cart - Mettre à jour quantité
 * DELETE /api/cart - Retirer du panier
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

require_once __DIR__ . '/../../models/Cart.php';

try {
    $cart = new Cart();
    $customerId = $_SESSION['customer_id'];
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Récupérer le panier
            $items = $cart->getItems($customerId);
            $total = $cart->getTotal($customerId);
            $count = $cart->countItems($customerId);
            
            http_response_code(200);
            echo json_encode([
                'items' => $items,
                'total' => $total,
                'count' => $count
            ]);
            break;
            
        case 'POST':
            // Ajouter au panier
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['configuration_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'configuration_id requis']);
                exit;
            }
            
            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
            $cart->addItem($customerId, $data['configuration_id'], $quantity);
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Ajouté au panier'
            ]);
            break;
            
        case 'PUT':
            // Mettre à jour la quantité
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['configuration_id']) || !isset($data['quantity'])) {
                http_response_code(400);
                echo json_encode(['error' => 'configuration_id et quantity requis']);
                exit;
            }
            
            $cart->updateQuantity($customerId, $data['configuration_id'], $data['quantity']);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Quantité mise à jour'
            ]);
            break;
            
        case 'DELETE':
            // Retirer du panier ou vider le panier
            if (isset($_GET['configuration_id'])) {
                // Retirer un item spécifique
                $cart->removeItem($customerId, $_GET['configuration_id']);
                $message = 'Retiré du panier';
            } else {
                // Vider tout le panier
                $cart->clear($customerId);
                $message = 'Panier vidé';
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
