<?php
/**
 * API: Gestion du panier de façades
 * GET /api/cart/facades - Lister les façades du panier
 * POST /api/cart/facades - Ajouter une façade au panier
 * PUT /api/cart/facades - Mettre à jour quantité
 * DELETE /api/cart/facades - Retirer du panier
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialiser la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../core/Session.php';
    Session::getInstance();
}

// Vérifier l'authentification
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

try {
    $db = Database::getInstance()->getPDO();
    $customerId = $_SESSION['customer_id'];

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Récupérer les façades du panier
            $stmt = $db->prepare("
                SELECT id, config_data, quantity, unit_price, created_at
                FROM facade_cart_items
                WHERE customer_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$customerId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Décoder les config_data JSON
            foreach ($items as &$item) {
                $item['config'] = json_decode($item['config_data'], true);
                unset($item['config_data']);
            }

            // Calculer le total
            $total = array_reduce($items, function($sum, $item) {
                return $sum + ($item['unit_price'] * $item['quantity']);
            }, 0);

            http_response_code(200);
            echo json_encode([
                'items' => $items,
                'total' => $total,
                'count' => count($items)
            ]);
            break;

        case 'POST':
            // Ajouter une façade au panier
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['config']) || !isset($data['price'])) {
                http_response_code(400);
                echo json_encode(['error' => 'config et price requis']);
                exit;
            }

            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
            $configJson = json_encode($data['config']);

            $stmt = $db->prepare("
                INSERT INTO facade_cart_items (customer_id, config_data, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$customerId, $configJson, $quantity, $data['price']]);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Façade ajoutée au panier',
                'id' => $db->lastInsertId()
            ]);
            break;

        case 'PUT':
            // Mettre à jour la quantité
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id']) || !isset($data['quantity'])) {
                http_response_code(400);
                echo json_encode(['error' => 'id et quantity requis']);
                exit;
            }

            $stmt = $db->prepare("
                UPDATE facade_cart_items
                SET quantity = ?
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$data['quantity'], $data['id'], $customerId]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Quantité mise à jour'
            ]);
            break;

        case 'DELETE':
            // Retirer du panier
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("
                    DELETE FROM facade_cart_items
                    WHERE id = ? AND customer_id = ?
                ");
                $stmt->execute([$_GET['id'], $customerId]);
                $message = 'Façade retirée du panier';
            } else {
                $stmt = $db->prepare("
                    DELETE FROM facade_cart_items
                    WHERE customer_id = ?
                ");
                $stmt->execute([$customerId]);
                $message = 'Panier de façades vidé';
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
    error_log("Facade cart error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
