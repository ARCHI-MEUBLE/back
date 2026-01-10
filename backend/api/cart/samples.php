<?php
/**
 * API Panier - Gestion des échantillons
 * GET /api/cart/samples - Récupérer les échantillons du panier
 * POST /api/cart/samples - Ajouter un échantillon au panier
 * PUT /api/cart/samples - Mettre à jour la quantité
 * DELETE /api/cart/samples - Retirer un échantillon
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Database.php';

/**
 * Convertit un chemin relatif d'image en URL complète
 */
function convertImagePath($imagePath) {
    if (!$imagePath) {
        return null;
    }

    // Si c'est déjà une URL complète, la retourner telle quelle
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }

    // S'assurer que le chemin commence par /
    if (strpos($imagePath, '/') !== 0) {
        $imagePath = '/' . $imagePath;
    }

    // Détecter l'environnement
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

    if ($isLocal) {
        // En local, on retourne un chemin relatif pour que Next.js le gère via son proxy
        return $imagePath;
    }

    // EN PRODUCTION: Les images sont sur Railway backend
    $protocol = 'https';
    $baseUrl = $protocol . '://' . $host;

    return $baseUrl . $imagePath;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification client
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$customerId = $_SESSION['customer_id'];
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET - Récupérer les échantillons du panier
    if ($method === 'GET') {
        $query = "
            SELECT
                csi.id,
                csi.sample_color_id,
                csi.quantity,
                csi.created_at,
                sc.name as color_name,
                sc.hex,
                sc.image_url,
                sc.price_per_m2,
                sc.unit_price,
                st.name as type_name,
                st.material,
                st.description as type_description
            FROM cart_sample_items csi
            JOIN sample_colors sc ON csi.sample_color_id = sc.id
            JOIN sample_types st ON sc.type_id = st.id
            WHERE csi.customer_id = ?
            ORDER BY csi.created_at DESC
        ";

        $items = $db->query($query, [$customerId]);

        // Convertir les chemins d'images en URLs complètes
        foreach ($items as &$item) {
            if (isset($item['image_url'])) {
                $item['image_url'] = convertImagePath($item['image_url']);
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'items' => $items,
            'count' => count($items)
        ]);
        exit;
    }

    // POST - Ajouter un échantillon
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['sample_color_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'sample_color_id requis']);
            exit;
        }

        $sampleColorId = $input['sample_color_id'];
        $quantity = $input['quantity'] ?? 1;

        // Vérifier si l'échantillon existe déjà dans le panier
        $existing = $db->query(
            "SELECT id, quantity FROM cart_sample_items WHERE customer_id = ? AND sample_color_id = ?",
            [$customerId, $sampleColorId]
        );

        if (!empty($existing)) {
            // Déjà dans le panier
            http_response_code(400);
            echo json_encode([
                'error' => 'Cet échantillon est déjà dans votre panier'
            ]);
            exit;
        }

        // Insérer un nouvel article
        $db->execute(
            "INSERT INTO cart_sample_items (customer_id, sample_color_id, quantity) VALUES (?, ?, ?)",
            [$customerId, $sampleColorId, $quantity]
        );
        $itemId = $db->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Échantillon ajouté au panier',
            'item_id' => $itemId
        ]);
        exit;
    }

    // PUT - Mettre à jour la quantité
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['item_id']) || !isset($input['quantity'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id et quantity requis']);
            exit;
        }

        $itemId = $input['item_id'];
        $quantity = max(1, (int)$input['quantity']); // Minimum 1

        $result = $db->execute(
            "UPDATE cart_sample_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND customer_id = ?",
            [$quantity, $itemId, $customerId]
        );

        if ($result) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Quantité mise à jour'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Article non trouvé']);
        }
        exit;
    }

    // DELETE - Retirer un échantillon
    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['item_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id requis']);
            exit;
        }

        $itemId = $input['item_id'];

        $result = $db->execute(
            "DELETE FROM cart_sample_items WHERE id = ? AND customer_id = ?",
            [$itemId, $customerId]
        );

        if ($result) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Échantillon retiré du panier'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Article non trouvé']);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);

} catch (Exception $e) {
    error_log("[CART SAMPLES] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur serveur',
        'message' => $e->getMessage()
    ]);
}
