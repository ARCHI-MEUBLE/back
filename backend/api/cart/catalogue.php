<?php
/**
 * API: Gestion des articles du catalogue dans le panier
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getPDO();
    $customerId = $_SESSION['customer_id'];
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Récupérer les articles du catalogue dans le panier
            $query = "
                SELECT 
                    cci.id,
                    cci.catalogue_item_id,
                    cci.variation_id,
                    cci.quantity,
                    ci.name,
                    ci.unit_price,
                    ci.unit,
                    ci.image_url as item_image,
                    civ.color_name as variation_name,
                    civ.image_url as variation_image
                FROM cart_catalogue_items cci
                JOIN catalogue_items ci ON cci.catalogue_item_id = ci.id
                LEFT JOIN catalogue_item_variations civ ON cci.variation_id = civ.id
                WHERE cci.customer_id = ?
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$customerId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'items' => $items
            ]);
            break;
            
        case 'POST':
            // Ajouter au panier
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['catalogue_item_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'catalogue_item_id requis']);
                exit;
            }
            
            $catalogueItemId = $data['catalogue_item_id'];
            $variationId = $data['variation_id'] ?? null;
            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
            
            // Vérifier si l'item existe déjà avec la même variation
            $query = "SELECT id, quantity FROM cart_catalogue_items WHERE customer_id = ? AND catalogue_item_id = ? AND (variation_id = ? OR (variation_id IS NULL AND ? IS NULL))";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$customerId, $catalogueItemId, $variationId, $variationId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $newQuantity = $existing['quantity'] + $quantity;
                $stmt = $pdo->prepare("UPDATE cart_catalogue_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$newQuantity, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cart_catalogue_items (customer_id, catalogue_item_id, variation_id, quantity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$customerId, $catalogueItemId, $variationId, $quantity]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Article ajouté au panier']);
            break;
            
        case 'PUT':
            // Mettre à jour la quantité
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id']) || !isset($data['quantity'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id et quantity requis']);
                exit;
            }
            
            if ($data['quantity'] <= 0) {
                $stmt = $pdo->prepare("DELETE FROM cart_catalogue_items WHERE id = ? AND customer_id = ?");
                $stmt->execute([$data['id'], $customerId]);
            } else {
                $stmt = $pdo->prepare("UPDATE cart_catalogue_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND customer_id = ?");
                $stmt->execute([(int)$data['quantity'], $data['id'], $customerId]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Quantité mise à jour']);
            break;
            
        case 'DELETE':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("DELETE FROM cart_catalogue_items WHERE id = ? AND customer_id = ?");
                $stmt->execute([$_GET['id'], $customerId]);
                echo json_encode(['success' => true, 'message' => 'Article retiré du panier']);
            } else {
                $stmt = $pdo->prepare("DELETE FROM cart_catalogue_items WHERE customer_id = ?");
                $stmt->execute([$customerId]);
                echo json_encode(['success' => true, 'message' => 'Panier vidé']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
